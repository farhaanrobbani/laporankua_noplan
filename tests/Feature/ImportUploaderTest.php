<?php

namespace Tests\Feature;

use App\Models\Import;
use App\Models\ImportData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportUploaderTest extends TestCase
{
    use RefreshDatabase;

    private function uploadedXlsx(array $sheets, string $filename = 'data_karyawan.xlsx'): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $first = true;

        foreach ($sheets as $name => $rows) {
            $sheet = $first ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $sheet->setTitle($name);

            foreach ($rows as $r => $row) {
                foreach (array_values($row) as $c => $value) {
                    $sheet->setCellValue([$c + 1, $r + 1], $value);
                }
            }

            $first = false;
        }

        $path = sys_get_temp_dir().'/upload_'.uniqid().'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        $contents = file_get_contents($path);
        unlink($path);

        return UploadedFile::fake()->createWithContent($filename, $contents);
    }

    public function test_upload_menampilkan_sheet_dan_pratinjau(): void
    {
        $user = User::factory()->create();

        $file = $this->uploadedXlsx([
            'Karyawan' => [['Nama', 'Gaji'], ['Budi', 5000000]],
            'Lainnya' => [['X'], ['Y']],
        ]);

        Livewire::actingAs($user)
            ->test('import-uploader')
            ->upload('file', [$file])
            ->assertHasNoErrors()
            ->assertSee('Karyawan')
            ->assertSee('Lainnya')
            ->assertSee('Budi');
    }

    public function test_file_bukan_excel_ditolak(): void
    {
        $user = User::factory()->create();

        $file = UploadedFile::fake()->create('catatan.txt', 10, 'text/plain');

        Livewire::actingAs($user)
            ->test('import-uploader')
            ->upload('file', [$file])
            ->assertHasErrors(['file']);
    }

    public function test_konfirmasi_membuat_import_dan_redirect(): void
    {
        $user = User::factory()->create();

        $file = $this->uploadedXlsx([
            'Karyawan' => [['Nama', 'Gaji'], ['Budi', 5000000], ['Siti', 6000000]],
        ]);

        Livewire::actingAs($user)
            ->test('import-uploader')
            ->upload('file', [$file])
            ->set('sheet', 'Karyawan')
            ->call('confirmImport')
            ->assertHasNoErrors()
            ->assertRedirect(route('imports.show', Import::latest()->first()));

        $import = Import::latest()->first();

        $this->assertSame($user->id, $import->user_id);
        $this->assertSame('data_karyawan.xlsx', $import->file_name);
        $this->assertSame('Karyawan', $import->sheet_name);
        // Queue sync saat testing: job langsung jalan
        $this->assertSame('success', $import->fresh()->status);
        $this->assertSame(2, $import->fresh()->imported_rows);
    }

    public function test_append_pendaftaran_hanya_menghitung_baris_baru(): void
    {
        $user = User::factory()->create();

        $file1 = $this->uploadedXlsx([
            'Data' => [
                ['Nomor Daftar', 'Nama Suami', 'Nama Istri'],
                ['ND001', 'Budi', 'Siti'],
                ['ND002', 'Andi', 'Aya'],
            ],
        ], 'laporan-pendaftaran-nikah-09-2026.xlsx');

        Livewire::actingAs($user)
            ->test('import-uploader')
            ->upload('file', [$file1])
            ->set('sheet', 'Data')
            ->call('confirmImport')
            ->assertHasNoErrors();

        $first = Import::latest('id')->first();

        $this->assertSame('laporan pendaftaran nikah', $first->table_name);
        $this->assertSame('No. Daftar', $first->dedup_column);
        $this->assertSame('success', $first->fresh()->status);
        $this->assertSame(2, $first->fresh()->imported_rows);

        $file2 = $this->uploadedXlsx([
            'Data' => [
                ['Nomor Daftar', 'Nama Suami', 'Nama Istri'],
                ['ND001', 'Budi', 'Siti'],
                ['ND002', 'Andi', 'Aya'],
                ['ND003', 'Cecep', 'Imas'],
            ],
        ], 'laporan-pendaftaran-nikah-09-2026.xlsx');

        Livewire::actingAs($user)
            ->test('import-uploader')
            ->upload('file', [$file2])
            ->set('sheet', 'Data')
            ->call('confirmImport')
            ->assertHasNoErrors();

        $second = Import::latest('id')->first();

        $this->assertNotSame($first->id, $second->id);
        $this->assertTrue((bool) $second->fresh()->is_append);
        $this->assertSame('appended', $second->fresh()->status);
        // Hanya ND003 yang baru; ND001/ND002 di-skip sebagai baris lama.
        $this->assertSame(1, $second->fresh()->imported_rows);
        $this->assertSame(1, $second->importData()->count());

        // Link Data tampil untuk record appended.
        $this->actingAs($user)
            ->get(route('imports.show', $second))
            ->assertOk()
            ->assertSee(route('imports.data', $second));
    }

    public function test_append_tanpa_baris_baru_menghapus_file_dan_menampilkan_notifikasi(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $rows = [
            'Data' => [
                ['Nomor Daftar', 'Nama Suami', 'Nama Istri'],
                ['ND001', 'Budi', 'Siti'],
            ],
        ];

        $file1 = $this->uploadedXlsx($rows, 'laporan-pendaftaran-nikah-09-2026.xlsx');

        Livewire::actingAs($user)
            ->test('import-uploader')
            ->upload('file', [$file1])
            ->set('sheet', 'Data')
            ->call('confirmImport')
            ->assertHasNoErrors();

        $first = Import::latest('id')->first();

        $file2 = $this->uploadedXlsx($rows, 'laporan-pendaftaran-nikah-09-2026.xlsx');

        Livewire::actingAs($user)
            ->test('import-uploader')
            ->upload('file', [$file2])
            ->set('sheet', 'Data')
            ->call('confirmImport')
            ->assertHasNoErrors()
            ->assertRedirect(route('imports.index'));

        $this->assertDatabaseCount('imports', 1);
        $this->assertDatabaseHas('imports', ['id' => $first->id]);
        $this->assertCount(1, Storage::disk('local')->files('imports/user_'.$user->id));

        $this->actingAs($user)
            ->get(route('imports.index'))
            ->assertOk()
            ->assertSee('tidak ada data baru yang diambil');
    }

    public function test_upload_ulang_normal_tidak_menggandakan_baris(): void
    {
        $user = User::factory()->create();

        $file1 = $this->uploadedXlsx([
            'Data' => [
                ['Nomor Daftar', 'Nama Suami', 'Nama Istri'],
                ['ND001', 'Budi', 'Siti'],
                ['ND002', 'Andi', 'Aya'],
            ],
        ], 'laporan-pendaftaran-nikah-09-2026.xlsx');

        Livewire::actingAs($user)
            ->test('import-uploader')
            ->upload('file', [$file1])
            ->set('sheet', 'Data')
            ->call('confirmImport')
            ->assertHasNoErrors();

        $first = Import::latest('id')->first();

        $file2 = $this->uploadedXlsx([
            'Data' => [
                ['Nomor Daftar', 'Nama Suami', 'Nama Istri'],
                ['ND001', 'Budi Updated', 'Siti'],
                ['ND003', 'Cecep', 'Imas'],
            ],
        ], 'laporan-pendaftaran-nikah-10-2026.xlsx');

        Livewire::actingAs($user)
            ->test('import-uploader')
            ->upload('file', [$file2])
            ->set('sheet', 'Data')
            ->call('confirmImport')
            ->assertHasNoErrors();

        $second = Import::latest('id')->first();

        $this->assertNotSame($first->id, $second->id);
        $this->assertFalse((bool) $second->fresh()->is_append);
        $this->assertSame('success', $second->fresh()->status);
        // ND001 di-overwrite (pindah ke import baru), hanya ND003 yang benar-benar baru.
        $this->assertSame(1, $second->fresh()->imported_rows);

        $allRows = ImportData::whereIn('import_id', [$first->id, $second->id])->get();

        // Dengan fix: ND001 di-overwrite (pindah) → 3 baris unik (ND001, ND002, ND003).
        // Tanpa fix: ND001 terinsert ganda → 4 baris.
        $this->assertSame(3, $allRows->count());
        $this->assertSame(3, $allRows->pluck('dedup_key_value')->unique()->count());
        $this->assertSame(1, $allRows->where('dedup_key_value', 'ND001')->count());

        $nd1 = $allRows->firstWhere('dedup_key_value', 'ND001');
        $this->assertSame($second->id, $nd1->import_id);
        $this->assertSame('Budi Updated', $nd1->row_data['Nama Suami']);
    }

    public function test_append_l3_menyaring_baris_lama_pakai_nomor_perforasi(): void
    {
        $user = User::factory()->create();

        $file1 = $this->uploadedXlsx([
            'Data' => [
                ['No', 'Nomor Perforasi', 'Nama Catin', 'Tanggal Akad'],
                ['1', 'PF001', 'A - B', '2026-09-01'],
                ['2', 'PF002', 'C - D', '2026-09-02'],
            ],
        ], 'laporan-model-l3-09-2026.xlsx');

        Livewire::actingAs($user)
            ->test('import-uploader')
            ->upload('file', [$file1])
            ->set('sheet', 'Data')
            ->call('confirmImport')
            ->assertHasNoErrors();

        $first = Import::latest('id')->first();

        $this->assertSame('laporan model l3', $first->table_name);
        // Default dedup kini per tabel: l3 memakai Nomor Perforasi.
        $this->assertSame('Nomor Perforasi', $first->dedup_column);
        $this->assertSame('success', $first->fresh()->status);
        $this->assertSame(2, $first->fresh()->imported_rows);

        $file2 = $this->uploadedXlsx([
            'Data' => [
                ['No', 'Nomor Perforasi', 'Nama Catin', 'Tanggal Akad'],
                ['1', 'PF001', 'A - B', '2026-09-01'],
                ['2', 'PF002', 'C - D', '2026-09-02'],
                ['3', 'PF003', 'E - F', '2026-09-03'],
            ],
        ], 'laporan-model-l3-09-2026.xlsx');

        Livewire::actingAs($user)
            ->test('import-uploader')
            ->upload('file', [$file2])
            ->set('sheet', 'Data')
            ->call('confirmImport')
            ->assertHasNoErrors();

        $second = Import::latest('id')->first();

        $this->assertNotSame($first->id, $second->id);
        $this->assertTrue((bool) $second->fresh()->is_append);
        $this->assertSame('appended', $second->fresh()->status);
        // PF001/PF002 di-skip (sudah ada); hanya PF003 yang baru.
        $this->assertSame(1, $second->fresh()->imported_rows);
        $this->assertSame(1, $second->importData()->count());

        $pf1Count = ImportData::whereIn('import_id', [$first->id, $second->id])
            ->where('dedup_key_value', 'PF001')
            ->count();
        $this->assertSame(1, $pf1Count);
    }

    public function test_append_memeriksa_duplikat_dari_seluruh_riwayat_import(): void
    {
        $user = User::factory()->create();

        $file1 = $this->uploadedXlsx([
            'Data' => [
                ['Nomor Daftar', 'Nama Suami', 'Nama Istri'],
                ['ND001', 'Budi', 'Siti'],
                ['ND002', 'Andi', 'Aya'],
            ],
        ], 'laporan-pendaftaran-nikah-09-2026.xlsx');

        Livewire::actingAs($user)
            ->test('import-uploader')
            ->upload('file', [$file1])
            ->set('sheet', 'Data')
            ->call('confirmImport')
            ->assertHasNoErrors();

        $base = Import::latest('id')->first();

        $file2 = $this->uploadedXlsx([
            'Data' => [
                ['Nomor Daftar', 'Nama Suami', 'Nama Istri'],
                ['ND003', 'Cecep', 'Imas'],
            ],
        ], 'laporan-pendaftaran-nikah-09-2026.xlsx');

        Livewire::actingAs($user)
            ->test('import-uploader')
            ->upload('file', [$file2])
            ->set('sheet', 'Data')
            ->call('confirmImport')
            ->assertHasNoErrors();

        $second = Import::latest('id')->first();

        $this->assertTrue((bool) $second->fresh()->is_append);
        $this->assertSame(1, $second->fresh()->imported_rows);

        $file3 = $this->uploadedXlsx([
            'Data' => [
                ['Nomor Daftar', 'Nama Suami', 'Nama Istri'],
                ['ND001', 'Budi', 'Siti'],
                ['ND003', 'Cecep', 'Imas'],
                ['ND004', 'Imam', 'Ain'],
            ],
        ], 'laporan-pendaftaran-nikah-09-2026.xlsx');

        Livewire::actingAs($user)
            ->test('import-uploader')
            ->upload('file', [$file3])
            ->set('sheet', 'Data')
            ->call('confirmImport')
            ->assertHasNoErrors();

        $third = Import::latest('id')->first();

        $this->assertNotSame($second->id, $third->id);
        $this->assertTrue((bool) $third->fresh()->is_append);
        $this->assertSame('appended', $third->fresh()->status);
        // ND001/ND003 sudah ada di rentetan riwayat (bukan hanya import terakhir) → hanya ND004 yang masuk.
        $this->assertSame(1, $third->fresh()->imported_rows);
        $this->assertSame(1, $third->importData()->count());
        $this->assertSame(['ND004'], $third->importData()->pluck('dedup_key_value')->all());

        $total = ImportData::whereIn('import_id', [$base->id, $second->id, $third->id])->count();
        $this->assertSame(4, $total);
    }

    public function test_append_mengecek_duplikat_lintas_nama_file(): void
    {
        $user = User::factory()->create();

        $file1 = $this->uploadedXlsx([
            'Data' => [
                ['Nomor Daftar', 'Nama Suami', 'Nama Istri'],
                ['ND001', 'Budi', 'Siti'],
                ['ND002', 'Andi', 'Aya'],
            ],
        ], 'laporan-pendaftaran-nikah-09-2026.xlsx');

        Livewire::actingAs($user)
            ->test('import-uploader')
            ->upload('file', [$file1])
            ->set('sheet', 'Data')
            ->call('confirmImport')
            ->assertHasNoErrors();

        $september = Import::latest('id')->first();

        $file2 = $this->uploadedXlsx([
            'Data' => [
                ['Nomor Daftar', 'Nama Suami', 'Nama Istri'],
                ['ND003', 'Cecep', 'Imas'],
            ],
        ], 'laporan-pendaftaran-nikah-10-2026.xlsx');

        Livewire::actingAs($user)
            ->test('import-uploader')
            ->upload('file', [$file2])
            ->set('sheet', 'Data')
            ->call('confirmImport')
            ->assertHasNoErrors();

        $oktober = Import::latest('id')->first();

        $this->assertFalse((bool) $oktober->fresh()->is_append);
        $this->assertSame(1, $oktober->fresh()->imported_rows);

        $file3 = $this->uploadedXlsx([
            'Data' => [
                ['Nomor Daftar', 'Nama Suami', 'Nama Istri'],
                ['ND001', 'Budi', 'Siti'],
                ['ND002', 'Andi', 'Aya'],
                ['ND004', 'Imam', 'Ain'],
            ],
        ], 'laporan-pendaftaran-nikah-10-2026.xlsx');

        Livewire::actingAs($user)
            ->test('import-uploader')
            ->upload('file', [$file3])
            ->set('sheet', 'Data')
            ->call('confirmImport')
            ->assertHasNoErrors();

        $third = Import::latest('id')->first();

        $this->assertTrue((bool) $third->fresh()->is_append);
        // ND001/ND002 berasal dari file bulan lain tetap dianggap duplikat; hanya ND004 yang masuk.
        $this->assertSame(1, $third->fresh()->imported_rows);
        $this->assertSame(['ND004'], $third->importData()->pluck('dedup_key_value')->all());

        $total = ImportData::whereIn('import_id', [$september->id, $oktober->id, $third->id])->count();
        $this->assertSame(4, $total);
    }

    public function test_l3_cetak_maju_tidak_jadi_duplikat(): void
    {
        $user = User::factory()->create();

        $file = $this->uploadedXlsx([
            'Data' => [
                ['No', 'Nomor Perforasi', 'Nama Catin', 'Tanggal Akad'],
                ['1', 'PF-M-001', 'A - B', '2026-09-05'],
                ['2', 'PF-M-002', 'C - D', '2026-10-01'],
                ['3', 'PF-M-003', 'E - F', '2026-08-15'],
                ['4', 'PF-M-004', 'G - H', 'bukan-tanggal'],
                ['5', 'PF-M-005', 'I - J', '2020-01-01'],
            ],
        ], 'laporan-model-l3-09-2026.xlsx');

        Livewire::actingAs($user)
            ->test('import-uploader')
            ->upload('file', [$file])
            ->set('sheet', 'Data')
            ->call('confirmImport')
            ->assertHasNoErrors();

        $import = Import::latest('id')->first();

        $this->assertSame('success', $import->fresh()->status);

        $rows = $import->importData()->get()->mapWithKeys(
            fn (ImportData $r) => [$r->row_data['Nomor Perforasi'] => $r->row_data]
        );

        // Sesuai bulan file → Bukan Duplikat, Tanggal Cetak = Tanggal Akad.
        $this->assertSame('Bukan Duplikat', $rows['PF-M-001']['Keterangan']);
        $this->assertSame('2026-09-05', $rows['PF-M-001']['Tanggal Cetak']);

        // Cetak maju (akad setelah bulan file) → Bukan Duplikat, pindah ke bulan akad.
        $this->assertSame('Bukan Duplikat', $rows['PF-M-002']['Keterangan']);
        $this->assertSame('2026-10-01', $rows['PF-M-002']['Tanggal Cetak']);

        // Akad bulan sebelumnya masih dalam toleransi 1 bulan → Bukan Duplikat,
        // Tanggal Cetak diwarisi dari baris sebelumnya.
        $this->assertSame('Bukan Duplikat', $rows['PF-M-003']['Keterangan']);
        $this->assertSame('2026-09-05', $rows['PF-M-003']['Tanggal Cetak']);

        // Tanggal tidak ter-parse → tetap Duplikat.
        $this->assertSame('Duplikat', $rows['PF-M-004']['Keterangan']);
        $this->assertSame('2026-09-05', $rows['PF-M-004']['Tanggal Cetak']);

        // Tanggal jauh di luar toleransi 1 bulan → Duplikat.
        $this->assertSame('Duplikat', $rows['PF-M-005']['Keterangan']);
        $this->assertSame('2026-09-05', $rows['PF-M-005']['Tanggal Cetak']);
    }

    public function test_l3_status_rusak_tidak_menjadi_duplikat(): void
    {
        $user = User::factory()->create();

        $file = $this->uploadedXlsx([
            'Data' => [
                ['No', 'Nomor Perforasi', 'Nama Catin', 'Tanggal Akad', 'Status'],
                ['1', 'PF-R-001', 'A - B', '2026-09-05', 'SUDAH DIGUNAKAN'],
                ['2', 'PF-R-002', '', '', 'RUSAK'],
                ['3', 'PF-R-003', 'E - F', '', 'SUDAH DIGUNAKAN'],
            ],
        ], 'laporan-model-l3-09-2026.xlsx');

        Livewire::actingAs($user)
            ->test('import-uploader')
            ->upload('file', [$file])
            ->set('sheet', 'Data')
            ->call('confirmImport')
            ->assertHasNoErrors();

        $import = Import::latest('id')->first();
        $rows = $import->importData()->get()->mapWithKeys(
            fn (ImportData $r) => [$r->row_data['Nomor Perforasi'] => $r->row_data]
        );

        // Buku rusak (Status RUSAK, Tanggal Akad kosong) → "Rusak", bukan "Duplikat".
        $this->assertSame('Rusak', $rows['PF-R-002']['Keterangan']);
        $this->assertSame('2026-09-05', $rows['PF-R-002']['Tanggal Cetak']);

        // Baris RUSAK tidak menggeser rantai pewarisan Tanggal Cetak.
        $this->assertSame('Duplikat', $rows['PF-R-003']['Keterangan']);
        $this->assertSame('2026-09-05', $rows['PF-R-003']['Tanggal Cetak']);
    }
}
