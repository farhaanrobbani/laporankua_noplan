<?php

namespace Tests\Feature;

use App\Models\Import;
use App\Models\ImportData;
use App\Models\User;
use App\Services\ExcelImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    private function makeXlsx(array $rows, string $sheetName = 'Sheet1'): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetName);

        foreach ($rows as $r => $row) {
            foreach (array_values($row) as $c => $value) {
                $sheet->setCellValue([$c + 1, $r + 1], $value);
            }
        }

        $path = sys_get_temp_dir().'/test_'.uniqid().'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    private function storeXlsx(User $user, array $rows): array
    {
        $tmp = $this->makeXlsx($rows);
        $path = 'imports/user_'.$user->id.'/'.uniqid('test_', true).'.xlsx';
        Storage::disk('local')->put($path, file_get_contents($tmp));
        unlink($tmp);

        return [$path, Storage::disk('local')->path($path)];
    }

    public function test_guest_tidak_bisa_akses_halaman_import(): void
    {
        $this->get('/imports')->assertRedirect('/login');
        $this->get('/imports/upload')->assertRedirect('/login');
    }

    public function test_user_melihat_riwayat_import_miliknya(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Import::factory()->for($user)->create(['file_name' => 'milikku.xlsx']);
        Import::factory()->for($other)->create(['file_name' => 'milik_orang.xlsx']);

        $response = $this->actingAs($user)->get('/imports');

        $response->assertOk();
        $response->assertSee('milikku.xlsx');
        $response->assertDontSee('milik_orang.xlsx');
    }

    public function test_daftar_file_ringkas_dan_semua_nama_tetap_tersedia(): void
    {
        $user = User::factory()->create();

        foreach (['januari.xlsx', 'februari.xlsx', 'maret.xlsx'] as $fileName) {
            Import::factory()->for($user)->create([
                'file_name' => $fileName,
                'table_name' => 'laporan model l3',
            ]);
        }

        $this->actingAs($user)->get('/imports')
            ->assertOk()
            ->assertSee('3 file')
            ->assertSee('maret.xlsx')
            ->assertSee('februari.xlsx')
            ->assertSee('januari.xlsx');
    }

    public function test_service_import_menyimpan_data_dan_status(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        [$path] = $this->storeXlsx($user, [
            ['Nama', 'Tanggal', 'Nilai'],
            ['Budi', '2026-09-01', 100000],
            ['Siti', '2026-09-02', 250000],
            [null, null, null],
            ['Andi', '2026-09-03', 50000],
        ]);

        $import = Import::factory()->for($user)->create([
            'file_name' => 'data.xlsx',
            'file_path' => $path,
            'sheet_name' => 'Sheet1',
            'status' => 'pending',
        ]);

        app(ExcelImportService::class)->import($import->fresh());

        $import->refresh();

        $this->assertSame('success', $import->status);
        $this->assertSame(4, $import->total_rows);
        $this->assertSame(3, $import->imported_rows);
        $this->assertSame(1, $import->failed_rows);
        $this->assertCount(3, ImportData::where('import_id', $import->id)->get());
        $this->assertNotNull($import->imported_at);

        $first = ImportData::where('import_id', $import->id)->orderBy('row_number')->first();
        $this->assertSame('Budi', $first->row_data['Nama']);
        $this->assertEquals(100000, $first->row_data['Nilai']);

        $this->assertNotEmpty($import->error_log);
        $this->assertSame(4, $import->error_log[0]['row']);
    }

    public function test_service_import_gagal_sheet_kosong(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        [$path] = $this->storeXlsx($user, []);

        $import = Import::factory()->for($user)->create([
            'file_path' => $path,
            'sheet_name' => 'Sheet1',
            'status' => 'pending',
        ]);

        app(ExcelImportService::class)->import($import->fresh());

        $this->assertSame('failed', $import->fresh()->status);
        $this->assertNotEmpty($import->fresh()->error_log);
    }

    public function test_service_import_gagal_file_hilang(): void
    {
        $user = User::factory()->create();

        $import = Import::factory()->for($user)->create([
            'file_path' => 'imports/tidak_ada.xlsx',
            'status' => 'pending',
        ]);

        app(ExcelImportService::class)->import($import->fresh());

        $this->assertSame('failed', $import->fresh()->status);
    }

    public function test_halaman_detail_hanya_untuk_pemilik(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $import = Import::factory()->for($user)->create(['file_name' => 'dataku.xlsx']);

        $this->actingAs($user)->get("/imports/{$import->id}")->assertOk()->assertSee('dataku.xlsx');
        $this->actingAs($other)->get("/imports/{$import->id}")->assertForbidden();
    }

    public function test_pemilik_bisa_hapus_import(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        [$path] = $this->storeXlsx($user, [['Nama'], ['Budi']]);

        $import = Import::factory()->for($user)->create(['file_path' => $path]);
        ImportData::factory()->for($import)->create();

        Storage::disk('local')->assertExists($path);

        $this->actingAs($user)->delete("/imports/{$import->id}")->assertRedirect('/imports');

        $this->assertDatabaseMissing('imports', ['id' => $import->id]);
        $this->assertDatabaseMissing('import_data', ['import_id' => $import->id]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_bukan_pemilik_tidak_bisa_hapus(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $import = Import::factory()->for($user)->create();

        $this->actingAs($other)->delete("/imports/{$import->id}")->assertForbidden();
        $this->assertDatabaseHas('imports', ['id' => $import->id]);
    }
}
