<?php

namespace Tests\Feature;

use App\Models\Import;
use App\Models\ImportData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class DataTest extends TestCase
{
    use RefreshDatabase;

    private function importWithRows(User $user, array $rows = [['Budi', 100], ['Siti', 200], ['Andi', 300]]): Import
    {
        $import = Import::factory()->for($user)->create(['status' => 'success']);

        foreach ($rows as $i => $row) {
            ImportData::factory()->for($import)->create([
                'row_data' => ['Nama' => $row[0], 'Nilai' => $row[1]],
                'row_number' => $i + 2,
            ]);
        }

        return $import;
    }

    private function l3Import(User $user, array $rows, string $status = 'success'): Import
    {
        $import = Import::factory()->for($user)->create([
            'status' => $status,
            'table_name' => 'laporan model l3',
        ]);

        foreach ($rows as $i => $row) {
            ImportData::factory()->for($import)->create([
                'row_data' => $row,
                'row_number' => $i + 2,
            ]);
        }

        return $import;
    }

    public function test_guest_tidak_bisa_akses_data(): void
    {
        $this->get('/data/data-import')->assertRedirect('/login');
        $this->get('/data/export')->assertRedirect('/login');
        $this->get('/data/rusak')->assertRedirect('/login');
        $this->get('/data/skp')->assertRedirect('/login');
    }

    public function test_index_menampilkan_picker_dan_tabel(): void
    {
        $user = User::factory()->create();
        $import = $this->importWithRows($user);

        $response = $this->actingAs($user)->get('/data/data-import?import_id='.$import->id);

        $response->assertOk();
        $response->assertSee('Budi');
        $response->assertSee('Siti');
        $response->assertSee($import->table_name);
    }

    public function test_isolasi_data_antar_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $importB = $this->importWithRows($userB, [['Rahasia', 999]]);
        $importB->update(['table_name' => 'data_rahasia_user_b']);
        $recordB = $importB->importData()->first();

        // Picker tidak menampilkan import user lain
        $this->actingAs($userA)->get('/data/data-import')->assertOk()->assertDontSee('data_rahasia_user_b');

        // Akses langsung import user lain → 404
        $this->actingAs($userA)->get('/data/data-import?import_id='.$importB->id)->assertNotFound();

        // Detail record user lain → 403
        $this->actingAs($userA)->get('/data/'.$recordB->id)->assertForbidden();

        // Export data user lain → 404
        $this->actingAs($userA)->get('/data/export?import_id='.$importB->id)->assertNotFound();
    }

    public function test_detail_record_menampilkan_semua_kolom(): void
    {
        $user = User::factory()->create();
        $import = $this->importWithRows($user);
        $record = $import->importData()->first();

        $this->actingAs($user)->get('/data/'.$record->id)
            ->assertOk()
            ->assertSee('Budi')
            ->assertSee('Nama')
            ->assertSee('Nilai');
    }

    public function test_pencarian_memfilter_baris(): void
    {
        $user = User::factory()->create();
        $import = $this->importWithRows($user);

        Livewire::actingAs($user)
            ->test('data-table', ['importId' => $import->id])
            ->set('search', 'Siti')
            ->assertSee('Siti')
            ->assertDontSee('Budi')
            ->assertDontSee('Andi');
    }

    public function test_pencarian_tidak_case_sensitive(): void
    {
        $user = User::factory()->create();
        $import = $this->importWithRows($user);

        Livewire::actingAs($user)
            ->test('data-table', ['importId' => $import->id])
            ->set('search', 'budi')
            ->assertSee('Budi')
            ->assertDontSee('Siti');
    }

    public function test_filter_kolom_bekerja(): void
    {
        $user = User::factory()->create();
        $import = $this->importWithRows($user);

        Livewire::actingAs($user)
            ->test('data-table', ['importId' => $import->id])
            ->set('filterColumn', 'Nama')
            ->set('filterValue', 'Andi')
            ->assertSee('Andi')
            ->assertDontSee('Budi');
    }

    public function test_sorting_kolom_bekerja(): void
    {
        $user = User::factory()->create();
        $import = $this->importWithRows($user);

        Livewire::actingAs($user)
            ->test('data-table', ['importId' => $import->id])
            ->call('sortBy', 'Nama')
            ->call('sortBy', 'Nama')
            ->assertSeeInOrder(['Siti', 'Budi', 'Andi']);
    }

    public function test_duplikat_default_urut_tanggal_cetak_terbaru(): void
    {
        $user = User::factory()->create();
        $this->l3Import($user, [
            ['Nomor Perforasi' => 'PF-DUP-001', 'Keterangan' => 'Duplikat', 'Tanggal Cetak' => '05-10-2026'],
            ['Nomor Perforasi' => 'PF-DUP-002', 'Keterangan' => 'Duplikat', 'Tanggal Cetak' => '20-09-2026'],
            ['Nomor Perforasi' => 'PF-DUP-003', 'Keterangan' => 'Duplikat', 'Tanggal Cetak' => '2026-01-15'],
            ['Nomor Perforasi' => 'PF-DUP-004', 'Keterangan' => 'Duplikat', 'Tanggal Cetak' => ''],
            ['Nomor Perforasi' => 'PF-DUP-005', 'Keterangan' => 'Duplikat', 'Tanggal Cetak' => 'bukan-tanggal'],
        ]);

        $this->actingAs($user)->get('/data/duplikat')
            ->assertOk()
            ->assertSeeInOrder(['2026-10-05', '2026-09-20', '2026-01-15']);
    }

    public function test_hapus_satu_record(): void
    {
        $user = User::factory()->create();
        $import = $this->importWithRows($user);
        $record = $import->importData()->first();

        Livewire::actingAs($user)
            ->test('data-table', ['importId' => $import->id])
            ->call('deleteRecord', $record->id);

        $this->assertDatabaseMissing('import_data', ['id' => $record->id]);
        $this->assertSame(2, $import->importData()->count());
    }

    public function test_hapus_banyak_record_sekaligus(): void
    {
        $user = User::factory()->create();
        $import = $this->importWithRows($user);
        $ids = $import->importData()->pluck('id')->map(fn ($id) => (int) $id)->all();

        Livewire::actingAs($user)
            ->test('data-table', ['importId' => $import->id])
            ->set('selected', [$ids[0], $ids[1]])
            ->call('deleteSelected');

        $this->assertSame(1, $import->importData()->count());
        $this->assertDatabaseMissing('import_data', ['id' => $ids[0]]);
    }

    public function test_mount_ditolak_untuk_import_milik_orang(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $importB = $this->importWithRows($userB);

        // Livewire 4.4.8 menangani ModelNotFoundException sebagai response 404.
        Livewire::actingAs($userA)
            ->test('data-table', ['importId' => $importB->id])
            ->assertNotFound();
    }

    public function test_export_excel_berisi_data_benar(): void
    {
        $user = User::factory()->create();
        $import = $this->importWithRows($user);

        $response = $this->actingAs($user)->get('/data/export?import_id='.$import->id);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->assertHeader('content-disposition');

        $tmp = sys_get_temp_dir().'/export_test_'.uniqid().'.xlsx';
        file_put_contents($tmp, $response->streamedContent() ?? $response->getContent());

        $sheet = IOFactory::load($tmp)->getActiveSheet();
        $this->assertSame('Nama', $sheet->getCell('A1')->getValue());
        $this->assertSame('Nilai', $sheet->getCell('B1')->getValue());
        $this->assertSame('Budi', $sheet->getCell('A2')->getValue());

        unlink($tmp);
    }

    public function test_export_menghormati_pencarian(): void
    {
        $user = User::factory()->create();
        $import = $this->importWithRows($user);

        $response = $this->actingAs($user)->get('/data/export?import_id='.$import->id.'&search=Siti');

        $response->assertOk();

        $tmp = sys_get_temp_dir().'/export_test_'.uniqid().'.xlsx';
        file_put_contents($tmp, $response->streamedContent() ?? $response->getContent());

        $rows = IOFactory::load($tmp)->getActiveSheet()->toArray();
        $this->assertCount(2, $rows); // header + 1 baris
        $this->assertSame('Siti', $rows[1][0]);

        unlink($tmp);
    }

    public function test_kolom_desa_muncul_setelah_keterangan(): void
    {
        $user = User::factory()->create(['daftar_desa' => ['SONOWANGI']]);
        $import = $this->l3Import($user, [
            ['Nomor Perforasi' => 'PF-DT-001', 'Keterangan' => 'Duplikat'],
        ]);

        Livewire::actingAs($user)
            ->test('data-table', [
                'importIds' => [$import->id],
                'filterColumn' => 'Keterangan',
                'filterValue' => 'Duplikat',
                'filterMode' => 'exact',
                'editableColumns' => ['Desa'],
            ])
            ->assertSeeInOrder(['Keterangan', 'Desa'])
            ->assertSee('list="options-desa"', false)
            ->assertSee('SONOWANGI');
    }

    public function test_update_cell_menyimpan_desa(): void
    {
        $user = User::factory()->create(['daftar_desa' => ['SONOWANGI']]);
        $import = $this->l3Import($user, [
            ['Nomor Perforasi' => 'PF-DT-002', 'Keterangan' => 'Duplikat'],
        ]);
        $record = $import->importData()->first();

        Livewire::actingAs($user)
            ->test('data-table', [
                'importIds' => [$import->id],
                'editableColumns' => ['Desa'],
            ])
            ->call('updateCell', $record->id, 'Desa', '  SONOWANGI  ');

        $this->assertSame('SONOWANGI', $record->fresh()->row_data['Desa']);

        // Kolom di luar editableColumns diabaikan.
        Livewire::actingAs($user)
            ->test('data-table', [
                'importIds' => [$import->id],
                'editableColumns' => ['Desa'],
            ])
            ->call('updateCell', $record->id, 'Keterangan', 'Bukan Duplikat');

        $this->assertSame('Duplikat', $record->fresh()->row_data['Keterangan']);
    }

    public function test_update_cell_sinkron_baris_duplikat_fisik(): void
    {
        $user = User::factory()->create(['daftar_desa' => ['TIRTOMARTO']]);
        $a = $this->l3Import($user, [['Nomor Perforasi' => 'PF-DT-003', 'Keterangan' => 'Duplikat']]);
        $b = $this->l3Import($user, [['Nomor Perforasi' => 'PF-DT-003', 'Keterangan' => 'Duplikat']], 'appended');
        $rowA = $a->importData()->first();
        $rowB = $b->importData()->first();

        Livewire::actingAs($user)
            ->test('data-table', [
                'importIds' => [$a->id, $b->id],
                'editableColumns' => ['Desa'],
            ])
            ->call('updateCell', $rowA->id, 'Desa', 'TIRTOMARTO');

        $this->assertSame('TIRTOMARTO', $rowA->fresh()->row_data['Desa']);
        $this->assertSame('TIRTOMARTO', $rowB->fresh()->row_data['Desa']);
    }

    public function test_update_cell_tanggal_cetak_normalisasi_dan_fanout_per_perforasi(): void
    {
        $user = User::factory()->create();
        $a = $this->l3Import($user, [['Nomor Perforasi' => 'PF-TC-001', 'Keterangan' => 'Duplikat', 'Tanggal Cetak' => '2026-09-01']]);
        $b = $this->l3Import($user, [['Nomor Perforasi' => 'PF-TC-001', 'Keterangan' => 'Duplikat', 'Tanggal Cetak' => '2026-09-01']], 'appended');
        $rowA = $a->importData()->first();
        $rowB = $b->importData()->first();

        Livewire::actingAs($user)
            ->test('data-table', [
                'importIds' => [$a->id, $b->id],
                'editableColumns' => ['Desa', 'Tanggal Cetak'],
            ])
            ->call('updateCell', $rowA->id, 'Tanggal Cetak', '05-10-2026');

        $this->assertSame('2026-10-05', $rowA->fresh()->row_data['Tanggal Cetak']);
        $this->assertSame('2026-10-05', $rowB->fresh()->row_data['Tanggal Cetak']);
    }

    public function test_update_cell_tanggal_cetak_value_invalid_ditolak(): void
    {
        $user = User::factory()->create();
        $import = $this->l3Import($user, [['Nomor Perforasi' => 'PF-TC-002', 'Keterangan' => 'Duplikat', 'Tanggal Cetak' => '2026-09-01']]);
        $record = $import->importData()->first();

        Livewire::actingAs($user)
            ->test('data-table', [
                'importIds' => [$import->id],
                'editableColumns' => ['Tanggal Cetak'],
            ])
            ->call('updateCell', $record->id, 'Tanggal Cetak', 'bukan-tanggal');

        $this->assertSame('2026-09-01', $record->fresh()->row_data['Tanggal Cetak']);
    }

    public function test_update_cell_tanggal_cetak_kosong_diizinkan(): void
    {
        $user = User::factory()->create();
        $import = $this->l3Import($user, [['Nomor Perforasi' => 'PF-TC-003', 'Keterangan' => 'Duplikat', 'Tanggal Cetak' => '2026-09-01']]);
        $record = $import->importData()->first();

        Livewire::actingAs($user)
            ->test('data-table', [
                'importIds' => [$import->id],
                'editableColumns' => ['Tanggal Cetak'],
            ])
            ->call('updateCell', $record->id, 'Tanggal Cetak', '');

        $this->assertSame('', $record->fresh()->row_data['Tanggal Cetak']);
    }

    public function test_tanggal_cetak_dirender_sebagai_input_date_dengan_nilai_dinormalisasi(): void
    {
        $user = User::factory()->create();
        $import = $this->l3Import($user, [['Nomor Perforasi' => 'PF-TC-004', 'Keterangan' => 'Duplikat', 'Tanggal Cetak' => '05-09-2026']]);
        $record = $import->importData()->first();

        Livewire::actingAs($user)
            ->test('data-table', [
                'importIds' => [$import->id],
                'filterColumn' => 'Keterangan',
                'filterValue' => 'Duplikat',
                'filterMode' => 'exact',
                'editableColumns' => ['Desa', 'Tanggal Cetak'],
            ])
            ->assertSee('type="date"', false)
            ->assertSee('value="2026-09-05"', false)
            ->assertSeeInOrder(['Keterangan', 'Tanggal Cetak']);
    }

    public function test_update_cell_ditolak_bukan_pemilik(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $importA = $this->l3Import($userA, [['Nomor Perforasi' => 'PF-DT-A', 'Keterangan' => 'Duplikat']]);
        $importB = $this->l3Import($userB, [['Nomor Perforasi' => 'PF-DT-B', 'Keterangan' => 'Duplikat']]);
        $recordB = $importB->importData()->first();

        Livewire::actingAs($userA)
            ->test('data-table', [
                'importIds' => [$importA->id],
                'editableColumns' => ['Desa'],
            ])
            ->call('updateCell', $recordB->id, 'Desa', 'SONOWANGI')
            ->assertStatus(403);

        $this->assertArrayNotHasKey('Desa', $recordB->fresh()->row_data);
    }

    public function test_mount_merge_ditolak_untuk_import_milik_orang(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $importB = $this->l3Import($userB, [['Nomor Perforasi' => 'PF-DT-X', 'Keterangan' => 'Duplikat']]);

        // Livewire 4.4.8 menangani ModelNotFoundException sebagai response 404.
        Livewire::actingAs($userA)
            ->test('data-table', ['importIds' => [$importB->id]])
            ->assertNotFound();
    }

    public function test_tab_rusak_mendeteksi_nomor_hilang_per_nomor(): void
    {
        $user = User::factory()->create();
        $this->l3Import($user, [
            ['Nomor Perforasi' => '116741001'],
            ['Nomor Perforasi' => 'JT 116741002'],
            ['Nomor Perforasi' => '116741004'],
            ['Nomor Perforasi' => '116741005'],
            ['Nomor Perforasi' => 'PF-DT-NONNUM'],
        ]);

        $response = $this->actingAs($user)->get('/data/rusak');

        $response->assertOk();
        // Celah interior: 003 hilang di tengah rentang 001..005.
        $response->assertSee('JT 116741003');
        // Di luar rentang = stok, bukan rusak.
        $response->assertDontSee('JT 116741006');
        // Nomor yang ada bukan rusak.
        $response->assertDontSee('JT 116741001');
        $response->assertSee('nomor terdeteksi rusak');
        $response->assertSee('116741');
    }

    public function test_tab_rusak_abaikan_celah_palsu_dari_reprint_lintas_bulan(): void
    {
        $user = User::factory()->create();
        $this->l3Import($user, [
            ['Nomor Perforasi' => '116741251', 'Tanggal Cetak' => '01-12-2025'],
            ['Nomor Perforasi' => '116741252', 'Tanggal Cetak' => '02-12-2025'],
            ['Nomor Perforasi' => '116741253', 'Tanggal Cetak' => '03-12-2025'],
            ['Nomor Perforasi' => '116741254', 'Tanggal Cetak' => '04-12-2025'],
            ['Nomor Perforasi' => '116741256', 'Tanggal Cetak' => '05-12-2025'],
            ['Nomor Perforasi' => '116741257', 'Tanggal Cetak' => '06-12-2025'],
            ['Nomor Perforasi' => '116741258', 'Tanggal Cetak' => '05-01-2026'],
            ['Nomor Perforasi' => '116741259', 'Tanggal Cetak' => '06-01-2026'],
            ['Nomor Perforasi' => '116741255', 'Tanggal Cetak' => '05-04-2026'],
            ['Nomor Perforasi' => '116741260', 'Tanggal Cetak' => '10-04-2026'],
            ['Nomor Perforasi' => '116741261', 'Tanggal Cetak' => '10-04-2026'],
            ['Nomor Perforasi' => '116741263', 'Tanggal Cetak' => '10-04-2026'],
        ]);

        $response = $this->actingAs($user)->get('/data/rusak');

        $response->assertOk();
        $response->assertViewHas('totalRusak', 1);
        $response->assertViewHas('prefixCount', 1);
        $response->assertSee('JT 116741262');
        $response->assertSee('2026-04');
        $response->assertDontSee('JT 116741256');
    }

    public function test_tab_rusak_menampilkan_baris_status_rusak_dari_excel(): void
    {
        $user = User::factory()->create();
        $this->l3Import($user, [
            ['Nomor Perforasi' => '116741001', 'Status' => 'SUDAH DIGUNAKAN', 'Tanggal Cetak' => '01-09-2026'],
            ['Nomor Perforasi' => '116741002', 'Status' => 'RUSAK', 'Tanggal Cetak' => '01-09-2026'],
            ['Nomor Perforasi' => '116741003', 'Status' => 'SUDAH DIGUNAKAN', 'Tanggal Cetak' => '01-09-2026'],
        ]);

        $response = $this->actingAs($user)->get('/data/rusak');

        $response->assertOk();
        $response->assertSee('buku berstatus');
        $response->assertSee('JT 116741002');
        $response->assertSee('01-09-2026');
        // Baris berstatus RUSAK bukan celah, jadi tidak muncul sebagai nomor hilang.
        $response->assertDontSee('nomor terdeteksi rusak');
        $response->assertDontSee('Tidak ada nomor rusak terdeteksi');
    }

    public function test_tab_rusak_memakai_10_baris_per_halaman_untuk_kedua_tabel(): void
    {
        $user = User::factory()->create();
        $rows = [];

        for ($i = 1; $i <= 12; $i++) {
            $rows[] = [
                'Nomor Perforasi' => '1167410'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'Status' => 'RUSAK',
                'Tanggal Cetak' => '01-09-2026',
            ];
        }

        $this->l3Import($user, $rows);

        $this->actingAs($user)->get('/data/rusak')
            ->assertOk()
            ->assertViewHas('missingRows', fn ($paginator) => $paginator->perPage() === 10)
            ->assertViewHas('rusakStatusRows', fn ($paginator) => $paginator->perPage() === 10)
            ->assertSee('JT 116741001')
            ->assertSee('JT 116741010')
            ->assertDontSee('JT 116741011');

        $this->actingAs($user)->get('/data/rusak?status_page=2')
            ->assertOk()
            ->assertSee('JT 116741011')
            ->assertSee('JT 116741012')
            ->assertDontSee('JT 116741001');
    }

    public function test_tab_rusak_tanpa_import_l3_tampil_empty_state(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/data/rusak');

        $response->assertOk();
        $response->assertSee('Belum ada data');
        $response->assertSee('Upload laporan model L3');
    }

    public function test_tab_rusak_tanpa_celah_tampil_tidak_ada_rusak(): void
    {
        $user = User::factory()->create();
        $this->l3Import($user, [
            ['Nomor Perforasi' => '116741001'],
            ['Nomor Perforasi' => '116741002'],
            ['Nomor Perforasi' => '116741003'],
        ]);

        $response = $this->actingAs($user)->get('/data/rusak');

        $response->assertOk();
        $response->assertSee('Tidak ada nomor rusak terdeteksi');
    }

    public function test_tab_rusak_isolasi_antar_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $this->l3Import($userA, [
            ['Nomor Perforasi' => '116741001'],
            ['Nomor Perforasi' => '116741004'],
        ]);

        $response = $this->actingAs($userB)->get('/data/rusak');

        $response->assertOk();
        $response->assertSee('Belum ada data');
        $response->assertDontSee('JT 116741002');
        $response->assertDontSee('JT 116741003');
    }

    public function test_tab_rusak_muncul_di_semua_halaman_menu_data(): void
    {
        $user = User::factory()->create();
        $this->l3Import($user, [['Nomor Perforasi' => '116741001']]);

        foreach (['/data/data-import', '/data/pelaksanaan-kantor', '/data/pelaksanaan-luar-kantor', '/data/duplikat', '/data/bukan-duplikat', '/data/rusak', '/data/skp'] as $uri) {
            $this->actingAs($user)->get($uri)
                ->assertOk()
                ->assertSee('/data/rusak', false)
                ->assertDontSee('/data/bukti-bayar', false)
                ->assertDontSee('/data/surat-tugas', false)
                ->assertSee('/data/skp', false);
        }
    }

    public function test_tab_pelaksanaan_tidak_error_dengan_join_column(): void
    {
        $user = User::factory()->create();

        $pn = Import::factory()->for($user)->create(['status' => 'success', 'table_name' => 'laporan peristiwa nikah']);
        ImportData::factory()->for($pn)->create([
            'row_data' => ['Nomor Daftar' => 'ND-001', 'Nikah Di' => 'KANTOR', 'Nama Suami' => 'Budi'],
            'row_number' => 2,
        ]);
        ImportData::factory()->for($pn)->create([
            'row_data' => ['Nomor Daftar' => 'ND-002', 'Nikah Di' => 'BEDOL', 'Nama Suami' => 'Agus'],
            'row_number' => 3,
        ]);

        $pdk = Import::factory()->for($user)->create(['status' => 'success', 'table_name' => 'laporan pendaftaran nikah']);
        ImportData::factory()->for($pdk)->create([
            'row_data' => ['Nomor Daftar' => 'ND-001', 'Nikah Di' => 'KANTOR', 'Nama Istri' => 'Siti'],
            'row_number' => 2,
        ]);
        ImportData::factory()->for($pdk)->create([
            'row_data' => ['Nomor Daftar' => 'ND-002', 'Nikah Di' => 'BEDOL', 'Nama Istri' => 'Aminah'],
            'row_number' => 3,
        ]);

        // Sebelum fix mergeByColumn tidak mengisi _row_id → ErrorException wire:key (500).
        $this->actingAs($user)->get('/data/pelaksanaan-kantor')
            ->assertOk()
            ->assertSee('Budi')
            ->assertSee('Siti')
            ->assertDontSee('Agus');

        $this->actingAs($user)->get('/data/pelaksanaan-luar-kantor')
            ->assertOk()
            ->assertSee('Agus')
            ->assertSee('Aminah')
            ->assertDontSee('Budi');
    }

    private function pdkLuarImport(User $user, array $rows, string $status = 'success'): Import
    {
        $import = Import::factory()->for($user)->create([
            'status' => $status,
            'table_name' => 'laporan pendaftaran nikah',
        ]);

        foreach ($rows as $i => $row) {
            ImportData::factory()->for($import)->create([
                'row_data' => array_merge([
                    'Nomor Daftar' => '',
                    'Tanggal Nikah' => '',
                    'Nikah Di' => 'LUAR KUA / BEDOL',
                ], $row),
                'row_number' => $i + 2,
            ]);
        }

        return $import;
    }

    private function pnImport(User $user, array $rows, string $status = 'success'): Import
    {
        $import = Import::factory()->for($user)->create([
            'status' => $status,
            'table_name' => 'laporan peristiwa nikah',
        ]);

        foreach ($rows as $i => $row) {
            ImportData::factory()->for($import)->create([
                'row_data' => array_merge([
                    'Nomor Daftar' => '',
                    'Tanggal Nikah' => '',
                ], $row),
                'row_number' => $i + 2,
            ]);
        }

        return $import;
    }

    private function seedSkpFixtures(User $user): void
    {
        // PDK: 2 pendaftaran September (1 luar, 1 kantor), 1 pendaftaran April (untuk kuartal).
        $this->pdkLuarImport($user, [
            ['Nomor Daftar' => 'ND-S1', 'Tanggal Nikah' => '10-09-2026', 'Tanggal Daftar' => '01-09-2026', 'Nikah Di' => 'LUAR KUA / BEDOL'],
            ['Nomor Daftar' => 'ND-S2', 'Tanggal Nikah' => '12-09-2026', 'Tanggal Daftar' => '02-09-2026', 'Nikah Di' => 'KUA / KANTOR'],
            ['Nomor Daftar' => 'ND-A1', 'Tanggal Nikah' => '15-04-2026', 'Tanggal Daftar' => '05-04-2026', 'Nikah Di' => 'KUA / KANTOR'],
        ]);

        // PN: pelaksanaan September (1 luar via join PDK, 1 kantor, 1 tanpa padanan PDK = kantor).
        $this->pnImport($user, [
            ['Nomor Daftar' => 'ND-S1', 'Tanggal Nikah' => '10-09-2026'],
            ['Nomor Daftar' => 'ND-S2', 'Tanggal Nikah' => '12-09-2026'],
            ['Nomor Daftar' => 'ND-ONLY', 'Tanggal Nikah' => '20-09-2026'],
        ]);

        // L3: 2 duplikat unik (perforasi 111 kembar dihitung 1) + 1 bukan duplikat.
        $this->l3Import($user, [
            ['Nomor Perforasi' => '111', 'Keterangan' => 'Duplikat', 'Tanggal Cetak' => '05-09-2026'],
            ['Nomor Perforasi' => '111', 'Keterangan' => 'Duplikat', 'Tanggal Cetak' => '2026-09-05'],
            ['Nomor Perforasi' => '222', 'Keterangan' => 'Duplikat', 'Tanggal Cetak' => '08-09-2026'],
            ['Nomor Perforasi' => '333', 'Keterangan' => 'Bukan Duplikat', 'Tanggal Cetak' => '08-09-2026'],
        ]);
    }

    public function test_skp_triwulan_menghitung_total_dan_rincian_bulanan(): void
    {
        $user = User::factory()->create();
        $this->seedSkpFixtures($user);

        $this->actingAs($user)->get('/data/skp?tw=3&tahun=2026')
            ->assertOk()
            ->assertSee('Statistik SKP')
            ->assertSee('TW3 2026')
            ->assertSee('Juli–September')
            ->assertViewHas('stats', function (array $stats) {
                $this->assertSame([
                    'pelaksanaan' => 3,
                    'pelaksanaan_kantor' => 2, // ND-S2 + ND-ONLY (tanpa padanan = kantor)
                    'pelaksanaan_luar' => 1,
                    'pendaftaran' => 2,
                    'pendaftaran_kantor' => 1,
                    'pendaftaran_luar' => 1,
                    'duplikat' => 2, // perforasi 111 dihitung sekali
                    'rusak' => 0,
                ], $stats['triwulan']);

                $this->assertSame($stats['triwulan'], $stats['bulan'][9]);
                $this->assertSame(
                    ['pelaksanaan' => 0, 'pelaksanaan_kantor' => 0, 'pelaksanaan_luar' => 0, 'pendaftaran' => 0, 'pendaftaran_kantor' => 0, 'pendaftaran_luar' => 0, 'duplikat' => 0, 'rusak' => 0],
                    $stats['bulan'][7]
                );
                $this->assertSame($stats['bulan'][7], $stats['bulan'][8]);

                // Tahunan: seluruh 2026, termasuk pendaftaran April.
                $this->assertSame([
                    'pelaksanaan' => 3,
                    'pelaksanaan_kantor' => 2,
                    'pelaksanaan_luar' => 1,
                    'pendaftaran' => 3,
                    'pendaftaran_kantor' => 2,
                    'pendaftaran_luar' => 1,
                    'duplikat' => 2,
                    'rusak' => 0,
                ], $stats['tahunan']);

                return true;
            });
    }

    public function test_skp_pendaftaran_lengkap_dan_card_tahunan(): void
    {
        $user = User::factory()->create();
        $this->seedSkpFixtures($user);

        $response = $this->actingAs($user)->get('/data/skp?tw=3&tahun=2026');
        $response->assertOk()
            ->assertSee('Pendaftaran Nikah Kantor September')
            ->assertSee('Pendaftaran Nikah Luar Kantor September')
            ->assertSee('Pendaftaran Nikah Kantor TW3 2026')
            ->assertSee('Total Tahunan 2026')
            ->assertSee('Pendaftaran Nikah Kantor 2026')
            ->assertSee('Buku Rusak 2026');

        $response->assertViewHas('stats', function (array $stats) {
            $this->assertSame(1, $stats['bulan'][9]['pendaftaran_kantor']);
            $this->assertSame(1, $stats['bulan'][9]['pendaftaran_luar']);
            $this->assertSame(1, $stats['triwulan']['pendaftaran_kantor']);
            $this->assertSame(1, $stats['triwulan']['pendaftaran_luar']);
            $this->assertSame(2, $stats['tahunan']['pendaftaran_kantor']);
            $this->assertSame(1, $stats['tahunan']['pendaftaran_luar']);

            return true;
        });

        $annual = $response->viewData('stats')['tahunan'];
        $tw2 = $this->actingAs($user)->get('/data/skp?tw=2&tahun=2026')->viewData('stats');
        $this->assertSame($annual, $tw2['tahunan'], 'Card tahunan tidak berubah saat ganti TW');
    }

    public function test_skp_triwulan_dua_di_luar_rentang_tiga(): void
    {
        $user = User::factory()->create();
        $this->seedSkpFixtures($user);

        // TW2 (April–Juni): hanya pendaftaran April yang masuk.
        $this->actingAs($user)->get('/data/skp?tw=2&tahun=2026')
            ->assertOk()
            ->assertSee('TW2 2026')
            ->assertSee('April–Juni')
            ->assertViewHas('stats', function (array $stats) {
                $this->assertSame(1, $stats['triwulan']['pendaftaran']);
                $this->assertSame(0, $stats['triwulan']['pelaksanaan']);
                $this->assertSame(0, $stats['triwulan']['duplikat']);
                $this->assertSame(1, $stats['bulan'][4]['pendaftaran']);
                $this->assertSame(3, $stats['tahunan']['pendaftaran'], 'Tahunan tetap 3, tidak terpengaruh TW');

                return true;
            });
    }

    public function test_skp_dedup_nomor_daftar_kembar_dihitung_sekali(): void
    {
        $user = User::factory()->create();
        $this->pnImport($user, [
            ['Nomor Daftar' => 'ND-DUP', 'Tanggal Nikah' => '10-09-2026'],
        ]);
        $this->pnImport($user, [
            ['Nomor Daftar' => 'ND-DUP', 'Tanggal Nikah' => '10-09-2026'],
        ], 'appended');
        $this->pdkLuarImport($user, [
            ['Nomor Daftar' => 'ND-DUP', 'Tanggal Nikah' => '10-09-2026', 'Tanggal Daftar' => '01-09-2026', 'Nikah Di' => 'KUA / KANTOR'],
        ]);
        $this->pdkLuarImport($user, [
            ['Nomor Daftar' => 'ND-DUP', 'Tanggal Nikah' => '10-09-2026', 'Tanggal Daftar' => '01-09-2026', 'Nikah Di' => 'KUA / KANTOR'],
        ], 'appended');

        $this->actingAs($user)->get('/data/skp?tw=3&tahun=2026')
            ->assertViewHas('stats', function (array $stats) {
                $this->assertSame([
                    'pelaksanaan' => 1,
                    'pelaksanaan_kantor' => 1,
                    'pelaksanaan_luar' => 0,
                    'pendaftaran' => 1,
                    'pendaftaran_kantor' => 1,
                    'pendaftaran_luar' => 0,
                    'duplikat' => 0,
                    'rusak' => 0,
                ], $stats['triwulan']);

                return true;
            });
    }

    public function test_skp_buku_rusak_status_dan_celah_per_bulan_triwulan(): void
    {
        $user = User::factory()->create();
        $this->l3Import($user, [
            // Juli: celah 002 (001 dan 003 ada).
            ['Nomor Perforasi' => '116741001', 'Keterangan' => 'Bukan Duplikat', 'Tanggal Cetak' => '05-07-2026'],
            ['Nomor Perforasi' => '116741003', 'Keterangan' => 'Bukan Duplikat', 'Tanggal Cetak' => '05-07-2026'],
            // Agustus: Status RUSAK (baris kembar dihitung sekali).
            ['Nomor Perforasi' => '116742001', 'Keterangan' => 'Rusak', 'Tanggal Cetak' => '10-08-2026'],
            ['Nomor Perforasi' => '116742001', 'Keterangan' => 'Rusak', 'Tanggal Cetak' => '10-08-2026'],
            // September: celah 005 + satu Status RUSAK.
            ['Nomor Perforasi' => '116743004', 'Keterangan' => 'Bukan Duplikat', 'Tanggal Cetak' => '02-09-2026'],
            ['Nomor Perforasi' => '116743006', 'Keterangan' => 'Bukan Duplikat', 'Tanggal Cetak' => '02-09-2026'],
            ['Nomor Perforasi' => '116744001', 'Keterangan' => 'Rusak', 'Tanggal Cetak' => '03-09-2026'],
            // Oktober di luar TW3 tidak ikut dihitung.
            ['Nomor Perforasi' => '116745001', 'Keterangan' => 'Rusak', 'Tanggal Cetak' => '04-10-2026'],
        ]);

        $this->actingAs($user)->get('/data/skp?tw=3&tahun=2026')
            ->assertOk()
            ->assertSee('Buku Rusak')
            ->assertViewHas('stats', function (array $stats) {
                $this->assertSame(1, $stats['bulan'][7]['rusak'], 'Juli: celah 002');
                $this->assertSame(1, $stats['bulan'][8]['rusak'], 'Agustus: Status RUSAK, dedup nomor kembar');
                $this->assertSame(2, $stats['bulan'][9]['rusak'], 'September: celah 005 + Status RUSAK');
                $this->assertSame(4, $stats['triwulan']['rusak'], 'TW3 = jumlah 3 bulan');
                $this->assertSame(5, $stats['tahunan']['rusak'], 'Tahunan termasuk Status RUSAK Oktober');

                return true;
            });
    }

    public function test_skp_isolasi_milik_user_saja(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $this->seedSkpFixtures($userA);
        $this->pdkLuarImport($userB, [
            ['Nomor Daftar' => 'ND-B1', 'Tanggal Daftar' => '01-09-2026'],
        ]);

        $this->actingAs($userA)->get('/data/skp?tw=3&tahun=2026')
            ->assertViewHas('stats', fn (array $stats) => $stats['triwulan']['pendaftaran'] === 2);

        $this->actingAs($userB)->get('/data/skp?tw=3&tahun=2026')
            ->assertViewHas('stats', fn (array $stats) => $stats['triwulan'] === [
                'pelaksanaan' => 0,
                'pelaksanaan_kantor' => 0,
                'pelaksanaan_luar' => 0,
                'pendaftaran' => 1,
                'pendaftaran_kantor' => 0,
                'pendaftaran_luar' => 1,
                'duplikat' => 0,
                'rusak' => 0,
            ]);
    }
}
