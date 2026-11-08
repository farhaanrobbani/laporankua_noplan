<?php

namespace Tests\Feature;

use App\Models\Import;
use App\Models\ImportData;
use App\Models\Report;
use App\Models\ReportTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;
use ZipArchive;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private function importWithRows(User $user): Import
    {
        $import = Import::factory()->for($user)->create(['status' => 'success']);

        foreach ([['Budi', 100], ['Siti', 200]] as $i => $row) {
            ImportData::factory()->for($import)->create([
                'row_data' => ['Nama' => $row[0], 'Nilai' => $row[1]],
                'row_number' => $i + 2,
            ]);
        }

        return $import;
    }

    public function test_guest_tidak_bisa_akses_laporan(): void
    {
        $this->get('/reports')->assertRedirect('/login');
        $this->get('/reports/create')->assertRedirect('/login');
    }

    public function test_halaman_builder_menampilkan_import_setelah_template_dipilih(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $import = $this->importWithRows($user);
        $template = ReportTemplate::factory()->for($user)->create([
            'name' => 'Template Uji',
            'is_global' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user)->get('/reports/create')
            ->assertOk()
            ->assertSee('Pilih template terlebih dahulu');

        Livewire::actingAs($user)
            ->test('report-builder')
            ->set('selectedTemplateId', $template->id)
            ->call('applySelectedTemplate')
            ->assertSee($import->table_name);
    }

    public function test_builder_mengecualikan_import_tanpa_data(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $import = $this->importWithRows($user);
        $import->update(['table_name' => 'laporan peristiwa nikah']);

        Import::factory()->for($user)->create([
            'status' => 'empty',
            'table_name' => 'laporan peristiwa nikah',
            'file_name' => 'laporan-peristiwa-nikah-10-2026.xlsx',
        ]);
        Import::factory()->for($user)->create([
            'status' => 'empty',
            'table_name' => 'laporan kosong',
            'file_name' => 'laporan-kosong-10-2026.xlsx',
        ]);

        $template = ReportTemplate::factory()->for($user)->create([
            'name' => 'Template Uji',
            'is_global' => true,
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test('report-builder')
            ->set('selectedTemplateId', $template->id)
            ->call('applySelectedTemplate')
            ->assertSee('laporan peristiwa nikah')
            ->assertDontSee('laporan kosong')
            ->assertSet('imports.0.id', $import->id)
            ->set('importId', $import->id)
            ->assertSee('2. Filter & Urutan');
    }

    public function test_generate_pdf_menyimpan_file(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $import = $this->importWithRows($user);

        Livewire::actingAs($user)
            ->test('report-builder')
            ->set('importId', $import->id)
            ->set('title', 'Laporan Uji PDF')
            ->set('format', 'pdf')
            ->call('generate')
            ->assertHasNoErrors()
            ->assertRedirect(route('reports.index'));

        $report = Report::latest()->first();

        $this->assertSame('Laporan Uji PDF', $report->title);
        $this->assertSame('generated', $this->reportStatus($report));
        $this->assertNotNull($report->fresh()->file_path);
        Storage::disk('local')->assertExists($report->fresh()->file_path);
        $this->assertStringEndsWith('.pdf', $report->fresh()->file_path);
    }

    public function test_generate_word_menghasilkan_docx_valid(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $import = $this->importWithRows($user);

        Livewire::actingAs($user)
            ->test('report-builder')
            ->set('importId', $import->id)
            ->set('title', 'Laporan Uji Word')
            ->set('format', 'word')
            ->call('generate')
            ->assertHasNoErrors();

        $report = Report::latest()->first()->fresh();
        $this->assertStringEndsWith('.docx', $report->file_path);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($report->file_path)));
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertStringContainsString('Budi', $xml);
        $this->assertStringContainsString('Laporan Uji Word', $xml);
    }

    public function test_generate_excel_dapat_dibaca_kembali(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $import = $this->importWithRows($user);

        Livewire::actingAs($user)
            ->test('report-builder')
            ->set('importId', $import->id)
            ->set('title', 'Laporan Uji Excel')
            ->set('format', 'excel')
            ->call('generate')
            ->assertHasNoErrors();

        $report = Report::latest()->first()->fresh();
        $this->assertStringEndsWith('.xlsx', $report->file_path);

        $sheet = IOFactory::load(Storage::disk('local')->path($report->file_path))->getActiveSheet();
        $this->assertSame('Nama', $sheet->getCell('A1')->getValue());
        $this->assertSame('Budi', $sheet->getCell('A2')->getValue());
    }

    public function test_format_print_menuju_halaman_cetak(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $import = $this->importWithRows($user);

        $component = Livewire::actingAs($user)
            ->test('report-builder')
            ->set('importId', $import->id)
            ->set('title', 'Laporan Cetak')
            ->set('format', 'print')
            ->call('generate')
            ->assertHasNoErrors();

        $report = Report::latest()->first();
        $component->assertRedirect(route('reports.print', $report));

        $this->actingAs($user)->get(route('reports.print', $report))
            ->assertOk()
            ->assertSee('Laporan Cetak')
            ->assertSee('Budi');
    }

    public function test_generate_tanpa_kolom_ditolak(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $import = $this->importWithRows($user);

        Livewire::actingAs($user)
            ->test('report-builder')
            ->set('importId', $import->id)
            ->set('title', 'Tanpa Kolom')
            ->set('format', 'pdf')
            ->set('fields', [])
            ->call('generate')
            ->assertHasErrors(['fields']);

        $this->assertSame(0, Report::count());
    }

    public function test_download_hanya_pemilik(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $import = $this->importWithRows($user);

        Livewire::actingAs($user)
            ->test('report-builder')
            ->set('importId', $import->id)
            ->set('title', 'Untuk Download')
            ->set('format', 'pdf')
            ->call('generate');

        $report = Report::latest()->first();

        $this->actingAs($user)->get(route('reports.download', $report))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($other)->get(route('reports.download', $report))->assertForbidden();
        $this->actingAs($other)->get(route('reports.show', $report))->assertForbidden();
    }

    public function test_hapus_laporan_menghapus_file(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $import = $this->importWithRows($user);

        Livewire::actingAs($user)
            ->test('report-builder')
            ->set('importId', $import->id)
            ->set('title', 'Hapus Saya')
            ->set('format', 'pdf')
            ->call('generate');

        $report = Report::latest()->first()->fresh();
        Storage::disk('local')->assertExists($report->file_path);

        $this->actingAs($other)->delete(route('reports.destroy', $report))->assertForbidden();

        $this->actingAs($user)->delete(route('reports.destroy', $report))->assertRedirect('/reports');
        $this->assertDatabaseMissing('reports', ['id' => $report->id]);
        Storage::disk('local')->assertMissing($report->file_path);
    }

    public function test_nb_menyertakan_baris_appended_dan_dibatasi_per_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $pdk = fn (User $owner, string $status, array $rows) => tap(
            Import::factory()->for($owner)->create([
                'status' => $status,
                'table_name' => 'laporan pendaftaran nikah',
            ]),
            function (Import $import) use ($rows) {
                foreach ($rows as $i => $row) {
                    ImportData::factory()->for($import)->create([
                        'row_data' => $row,
                        'row_number' => $i + 2,
                    ]);
                }
            }
        );

        $firstImport = $pdk($user, 'success', [
            ['Nomor Daftar' => 'NB-SEP-001', 'Tanggal Daftar' => '2026-09-05', 'Nama Suami' => 'Suami Nol Satu', 'Nama Istri' => 'Istri Nol Satu'],
            ['Nomor Daftar' => 'NB-SEP-002', 'Tanggal Daftar' => '2026-09-08', 'Nama Suami' => 'Suami Nol Dua', 'Nama Istri' => 'Istri Nol Dua'],
        ]);
        $pdk($user, 'appended', [
            ['Nomor Daftar' => 'NB-SEP-003', 'Tanggal Daftar' => '2026-09-12', 'Nama Suami' => 'Suami Nol Tiga', 'Nama Istri' => 'Istri Nol Tiga'],
        ]);
        $pdk($other, 'success', [
            ['Nomor Daftar' => 'NB-LAIN-001', 'Tanggal Daftar' => '2026-09-06', 'Nama Suami' => 'Suami Lain', 'Nama Istri' => 'Istri Lain'],
        ]);

        $report = Report::factory()->for($user)->create([
            'import_id' => $firstImport->id,
            'config_json' => [
                'fields' => ['Nomor Daftar', 'Tanggal Daftar', 'Nama Suami', 'Nama Istri'],
                'filter_month' => '9',
                'filter_year' => '2026',
                'table_layout' => ['type' => 'laporan_nb'],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('reports.print', $report));
        $response->assertOk()
            ->assertSee('Suami Nol Tiga')
            ->assertDontSee('Suami Lain');

        $entries = [];
        foreach ($response->viewData('dataset')['rows'] as $row) {
            foreach ($row['entries'] ?? [] as $entry) {
                $entries[] = $entry['Nomor Daftar'] ?? '';
            }
        }

        $this->assertSame(['NB-SEP-001', 'NB-SEP-002', 'NB-SEP-003'], $entries);
    }

    private function nbReportWithManual(User $user, array $manualData): Report
    {
        $import = Import::factory()->for($user)->create([
            'status' => 'success',
            'table_name' => 'laporan pendaftaran nikah',
        ]);

        foreach ([
            ['Nomor Daftar' => 'ND-001', 'Tanggal Daftar' => '2026-09-05', 'Nama Suami' => 'Suami A', 'Nama Istri' => 'Istri A'],
            ['Nomor Daftar' => 'ND-002', 'Tanggal Daftar' => '2026-09-05', 'Nama Suami' => 'Suami B', 'Nama Istri' => 'Istri B'],
            ['Nomor Daftar' => 'ND-003', 'Tanggal Daftar' => '2026-09-10', 'Nama Suami' => 'Suami C', 'Nama Istri' => 'Istri C'],
        ] as $i => $row) {
            ImportData::factory()->for($import)->create([
                'row_data' => $row,
                'row_number' => $i + 2,
            ]);
        }

        return Report::factory()->for($user)->create([
            'import_id' => $import->id,
            'config_json' => [
                'fields' => ['Nomor Daftar', 'Tanggal Daftar', 'Nama Suami', 'Nama Istri'],
                'filter_month' => '9',
                'filter_year' => '2026',
                'table_layout' => ['type' => 'laporan_nb'],
                'manual_data' => $manualData,
            ],
        ]);
    }

    public function test_laporan_nb_baris_rusak_manual_mengurangi_sisa_dan_jumlah(): void
    {
        $user = User::factory()->create();

        $report = $this->nbReportWithManual($user, [
            ['is_sisa_bulan_lalu' => true, 'tanggal' => '01', 'uraian' => 'Sisa bulan lalu', 'masuk' => '100', 'penerimaan' => ''],
            ['tanggal' => '30', 'uraian' => 'Rusak', 'masuk' => '', 'keluar' => '2', 'penerimaan' => '', 'is_rusak' => true],
        ]);

        $response = $this->actingAs($user)->get(route('reports.print', $report));
        $response->assertOk()->assertSee('Rusak');

        $rows = $response->viewData('dataset')['rows'];

        // Sisa bulan lalu tidak berubah.
        $this->assertSame(100, $rows[0]['sisa']);

        // Data 05 Sep (2 entri) lalu 10 Sep (1 entri).
        $this->assertSame(2, $rows[1]['keluar']);
        $this->assertSame(98, $rows[1]['sisa']);
        $this->assertSame(1, $rows[2]['keluar']);
        $this->assertSame(97, $rows[2]['sisa']);

        // Baris Rusak di akhir: tanggal sesuai isian, keluar mengurangi sisa.
        $rusak = $rows[3];
        $this->assertSame('Rusak', $rusak['uraian']);
        $this->assertSame('30/09/2026', $rusak['tanggal']);
        $this->assertSame(2, $rusak['keluar']);
        $this->assertSame(95, $rusak['sisa']);

        // Jumlah: masuk 100, keluar 3 data + 2 rusak, sisa akhir 95.
        $jumlah = null;
        foreach ($this->tableCellRows($response->getContent()) as $cells) {
            if (in_array('Jumlah', $cells, true)) {
                $jumlah = $cells;
            }
        }

        $this->assertNotNull($jumlah);
        $this->assertSame('100', $jumlah[3]);
        $this->assertSame('5', $jumlah[4]);
        $this->assertSame('95', $jumlah[5]);
    }

    public function test_laporan_nb_baris_rusak_tidak_menimpa_baris_data(): void
    {
        $user = User::factory()->create();

        // Tanggal rusak sengaja sama dengan tanggal yang punya data (05 Sep).
        $report = $this->nbReportWithManual($user, [
            ['is_sisa_bulan_lalu' => true, 'tanggal' => '01', 'uraian' => 'Sisa bulan lalu', 'masuk' => '100', 'penerimaan' => ''],
            ['tanggal' => '05', 'uraian' => 'Rusak', 'masuk' => '', 'keluar' => '1', 'penerimaan' => '', 'is_rusak' => true],
        ]);

        $response = $this->actingAs($user)->get(route('reports.print', $report));
        $response->assertOk()->assertSee('Suami A - Istri A Cs')->assertSee('Rusak');

        $rows = $response->viewData('dataset')['rows'];

        // Uraian pasangan pada 05 Sep tetap utuh, tidak ditimpa "Rusak".
        $this->assertSame('Suami A - Istri A Cs', $rows[1]['uraian']);
        $this->assertCount(2, $rows[1]['entries']);

        // Baris Rusak tetap terpisah di akhir.
        $rusak = $rows[3];
        $this->assertSame('Rusak', $rusak['uraian']);
        $this->assertSame('05/09/2026', $rusak['tanggal']);
        $this->assertSame(1, $rusak['keluar']);
        $this->assertSame(96, $rusak['sisa']);
    }

    public function test_report_builder_tombol_rusak_menambah_baris_manual(): void
    {
        $user = User::factory()->create();
        $import = $this->importWithRows($user);

        $component = Livewire::actingAs($user)
            ->test('report-builder')
            ->set('importId', $import->id)
            ->set('tableLayout', ['type' => 'laporan_nb'])
            ->call('addNbRusakRow')
            ->assertSet('manualData.0.uraian', 'Rusak')
            ->assertSet('manualData.0.is_rusak', true);

        $component
            ->set('title', 'NB Rusak Manual')
            ->set('format', 'print')
            ->set('filterMonth', '9')
            ->set('filterYear', '2026')
            ->call('generate')
            ->assertHasNoErrors();

        $manual = Report::latest()->first()->config_json['manual_data'] ?? [];

        $this->assertSame('Rusak', $manual[0]['uraian'] ?? null);
        $this->assertTrue((bool) ($manual[0]['is_rusak'] ?? false));
        $this->assertArrayHasKey('keluar', $manual[0] ?? []);
    }

    public function test_laporan_n_menyertakan_baris_appended(): void
    {
        $user = User::factory()->create();

        $pn = fn (string $status, array $rows) => tap(
            Import::factory()->for($user)->create([
                'status' => $status,
                'table_name' => 'laporan peristiwa nikah',
            ]),
            function (Import $import) use ($rows) {
                foreach ($rows as $i => $row) {
                    ImportData::factory()->for($import)->create([
                        'row_data' => $row,
                        'row_number' => $i + 2,
                    ]);
                }
            }
        );

        $firstImport = $pn('success', [
            ['Nomor Daftar' => 'PN-SEP-001', 'Nomor Akta Nikah' => 'AKTA-SEP-001', 'Tanggal Nikah' => '2026-09-05', 'Nama Suami' => 'Pengantin Satu', 'Nama Istri' => 'Pasangan Satu'],
        ]);
        $pn('appended', [
            ['Nomor Daftar' => 'PN-SEP-002', 'Nomor Akta Nikah' => 'AKTA-SEP-002', 'Tanggal Nikah' => '2026-09-11', 'Nama Suami' => 'Pengantin Dua', 'Nama Istri' => 'Pasangan Dua'],
        ]);

        $report = Report::factory()->for($user)->create([
            'import_id' => $firstImport->id,
            'config_json' => [
                'fields' => ['Nomor Daftar', 'Nomor Akta Nikah', 'Tanggal Nikah', 'Nama Suami', 'Nama Istri'],
                'filter_month' => '9',
                'filter_year' => '2026',
                'table_layout' => ['type' => 'laporan_n'],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('reports.print', $report));
        $response->assertOk()
            ->assertSee('Pengantin Satu')
            ->assertSee('Pengantin Dua')
            ->assertSee('AKTA-SEP-002');

        $entries = [];
        foreach ($response->viewData('dataset')['rows'] as $row) {
            foreach ($row['entries'] ?? [] as $entry) {
                $entries[] = $entry['Nomor Akta Nikah'] ?? '';
            }
        }

        $this->assertSame(['AKTA-SEP-001', 'AKTA-SEP-002'], $entries);
    }

    public function test_laporan_n_pindah_halaman_setelah_25_baris(): void
    {
        $user = User::factory()->create();

        $import = Import::factory()->for($user)->create([
            'status' => 'success',
            'table_name' => 'laporan peristiwa nikah',
        ]);

        // 30 tanggal berbeda → 30 baris data + 1 baris sisa bulan lalu = 31 baris.
        foreach (range(1, 30) as $day) {
            $dd = str_pad((string) $day, 2, '0', STR_PAD_LEFT);
            ImportData::factory()->for($import)->create([
                'row_data' => [
                    'Nomor Daftar' => 'PN-'.$dd,
                    'Nomor Akta Nikah' => 'AKTA-'.$dd,
                    'Tanggal Nikah' => '2026-09-'.$dd,
                    'Nama Suami' => 'Suami '.$day,
                    'Nama Istri' => 'Istri '.$day,
                ],
                'row_number' => $day + 1,
            ]);
        }

        $report = Report::factory()->for($user)->create([
            'import_id' => $import->id,
            'output_format' => 'print',
            'config_json' => [
                'fields' => ['Nomor Daftar', 'Nomor Akta Nikah', 'Tanggal Nikah', 'Nama Suami', 'Nama Istri'],
                'filter_month' => '9',
                'filter_year' => '2026',
                'table_layout' => ['type' => 'laporan_n'],
                'manual_data' => [
                    ['is_sisa_bulan_lalu' => true, 'uraian' => 'Sisa bulan lalu', 'masuk' => '40'],
                ],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('reports.print', $report));
        $response->assertOk();

        $html = $response->getContent();

        // Halaman 1 (25 baris) ditutup Jumlah Dipindahkan; halaman 2 dibuka Jumlah Pindahan + kop baru.
        $this->assertSame(1, substr_count($html, 'Jumlah Dipindahkan'));
        $this->assertSame(1, substr_count($html, 'Jumlah Pindahan dari halaman sebelumnya'));
        $this->assertSame(1, substr_count($html, 'page-break-before: always'));
        $this->assertSame(2, substr_count($html, 'BUKU STOK KHUSUS'), 'Kop halaman tercetak untuk halaman 1 dan 2');

        $rows = $this->tableCellRows($html);

        // Pindahan membawa akumulasi halaman 1: masuk 40, keluar 24 (1 sisa + 24 tanggal), sisa 16.
        $pindahan = collect($rows)->first(fn ($r) => ($r[2] ?? '') === 'Jumlah Pindahan dari halaman sebelumnya');
        $this->assertNotNull($pindahan);
        $this->assertSame('40', $pindahan[3]);
        $this->assertSame('24', $pindahan[4]);
        $this->assertSame('16', $pindahan[5]);

        // Jumlah tetap dihitung penuh: masuk 40, keluar 30, sisa akhir 10.
        $jumlah = collect($rows)->first(fn ($r) => ($r[2] ?? '') === 'Jumlah');
        $this->assertNotNull($jumlah);
        $this->assertSame('40', $jumlah[3]);
        $this->assertSame('30', $jumlah[4]);
        $this->assertSame('10', $jumlah[5]);
    }

    public function test_laporan_l1_duplikat_l3_tidak_double_count(): void
    {
        $user = User::factory()->create(['daftar_desa' => ['DESA A']]);

        $mk = fn (string $table, string $status, array $rows) => tap(
            Import::factory()->for($user)->create([
                'status' => $status,
                'table_name' => $table,
            ]),
            function (Import $import) use ($rows) {
                foreach ($rows as $i => $row) {
                    ImportData::factory()->for($import)->create([
                        'row_data' => $row,
                        'row_number' => $i + 2,
                    ]);
                }
            }
        );

        $pn = $mk('laporan peristiwa nikah', 'success', [
            ['Nomor Daftar' => 'ND-L1-001', 'Nomor Akta Nikah' => 'AK-L1-001', 'Tanggal Nikah' => '2026-09-05', 'Kelurahan' => 'DESA A', 'Nama Suami' => 'Suami A', 'Nama Istri' => 'Istri A'],
        ]);
        $mk('laporan model l3', 'success', [
            ['Nomor Perforasi' => 'PF-L1-001', 'Keterangan' => 'Duplikat', 'Tanggal Cetak' => '2026-09-01', 'Desa' => 'DESA A'],
        ]);
        $mk('laporan model l3', 'appended', [
            ['Nomor Perforasi' => 'PF-L1-001', 'Keterangan' => 'Duplikat', 'Tanggal Cetak' => '2026-09-01', 'Desa' => 'DESA A'],
            ['Nomor Perforasi' => 'PF-L1-002', 'Keterangan' => 'Duplikat', 'Tanggal Cetak' => '2026-09-01', 'Desa' => 'DESA A'],
        ]);

        $report = Report::factory()->for($user)->create([
            'import_id' => $pn->id,
            'config_json' => [
                'fields' => ['Kelurahan', 'Jumlah Nikah', 'Duplikat'],
                'filter_month' => '9',
                'filter_year' => '2026',
                'merged_import_ids' => [$pn->id],
                'table_layout' => ['type' => 'laporan_l1'],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('reports.print', $report));
        $response->assertOk();

        $rows = collect($response->viewData('dataset')['rows']);
        $desaA = $rows->firstWhere('Kelurahan', 'DESA A');

        $this->assertNotNull($desaA);
        // PF-L1-001 ada di success DAN appended → dihitung sekali; PF-L1-002 baru → total 2, bukan 3.
        $this->assertSame(2, $desaA['Duplikat']);
    }

    public function test_l4_filter_menggunakan_tanggal_akad_bukan_tanggal_setor(): void
    {
        $user = User::factory()->create();

        $sim = tap(
            Import::factory()->for($user)->create([
                'status' => 'success',
                'table_name' => 'laporan simponi',
            ]),
            function (Import $import) {
                $rows = [
                    // Akad 5 Sep, setor 25 Sep → tampil.
                    ['No Pendaftaran' => 'SP-001', 'Nama Kelurahan' => 'DESA A', 'Tanggal dan Jam Setor' => '2026-09-25 10:00:00', 'Tanggal Akad' => '2026-09-05', 'Nama Suami' => 'Penyetor Alpha', 'Nominal Setor' => '600000'],
                    // Akad 20 Sep, setor 1 Sep → tampil, urut sebelum Alpha jika pakai tanggal setor.
                    ['No Pendaftaran' => 'SP-002', 'Nama Kelurahan' => 'DESA A', 'Tanggal dan Jam Setor' => '2026-09-01 09:00:00', 'Tanggal Akad' => '2026-09-20', 'Nama Suami' => 'Penyetor Beta', 'Nominal Setor' => '600000'],
                    // Akad 2 Okt, setor 10 Sep → gugur karena akad di luar bulan filter.
                    ['No Pendaftaran' => 'SP-003', 'Nama Kelurahan' => 'DESA A', 'Tanggal dan Jam Setor' => '2026-09-10 08:00:00', 'Tanggal Akad' => '2026-10-02', 'Nama Suami' => 'Penyetor Gamma', 'Nominal Setor' => '600000'],
                ];
                foreach ($rows as $i => $row) {
                    ImportData::factory()->for($import)->create([
                        'row_data' => $row,
                        'row_number' => $i + 2,
                    ]);
                }
            }
        );

        $report = Report::factory()->for($user)->create([
            'import_id' => $sim->id,
            'config_json' => [
                'fields' => ['Nama Kelurahan', 'Tanggal dan Jam Setor', 'Nominal Setor', 'Tanggal Akad', 'Nama Suami'],
                'filter_month' => '9',
                'filter_year' => '2026',
                'table_layout' => [
                    'type' => 'grouped_detail',
                    'aggregation' => [
                        'group_by' => 'Nama Kelurahan',
                        'date_filter_field' => 'Tanggal Akad',
                    ],
                    'columns' => [
                        ['type' => 'row_number', 'label' => 'No'],
                        ['type' => 'field', 'field' => 'Nama Kelurahan', 'label' => 'Desa'],
                        ['type' => 'aggregate_total', 'label' => 'Jumlah Perkawinan'],
                        ['type' => 'field', 'field' => 'Tanggal dan Jam Setor', 'label' => 'Tanggal Setor', 'format' => 'date_id'],
                        ['type' => 'static', 'value' => '600000', 'label' => 'Jumlah Setor'],
                        ['type' => 'field', 'field' => 'Tanggal Akad', 'label' => 'Tanggal Perkawinan', 'format' => 'date_id'],
                        ['type' => 'field', 'field' => 'Nama Suami', 'label' => 'Penyetor'],
                    ],
                ],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('reports.print', $report));
        $response->assertOk()
            ->assertSee('Penyetor Alpha')
            ->assertSee('Penyetor Beta')
            ->assertDontSee('Penyetor Gamma');

        $rows = $response->viewData('dataset')['rows'];
        $this->assertCount(2, $rows);

        // Urutan dalam grup mengikuti Tanggal Akad (Alpha 5 Sep < Beta 20 Sep),
        // bukan tanggal setor (Beta 1 Sep < Alpha 25 Sep).
        $html = $response->getContent();
        $posAlpha = strpos($html, 'Penyetor Alpha');
        $posBeta = strpos($html, 'Penyetor Beta');
        $this->assertNotFalse($posAlpha);
        $this->assertNotFalse($posBeta);
        $this->assertLessThan($posBeta, $posAlpha);
    }

    public function test_l1_duplikat_terisi_setelah_desa_diisi_lewat_tab_duplikat(): void
    {
        $user = User::factory()->create(['daftar_desa' => ['SONOWANGI']]);

        $pn = tap(
            Import::factory()->for($user)->create([
                'status' => 'success',
                'table_name' => 'laporan peristiwa nikah',
            ]),
            function (Import $import) {
                ImportData::factory()->for($import)->create([
                    'row_data' => ['Nomor Daftar' => 'PN-L1D-001', 'Tanggal Nikah' => '2026-09-05', 'Kelurahan' => 'SONOWANGI', 'Nama Suami' => 'Suami L1D', 'Nama Istri' => 'Istri L1D'],
                    'row_number' => 2,
                ]);
            }
        );

        $l3 = tap(
            Import::factory()->for($user)->create([
                'status' => 'success',
                'table_name' => 'laporan model l3',
            ]),
            function (Import $import) {
                ImportData::factory()->for($import)->create([
                    'row_data' => [
                        'Nomor Perforasi' => 'PF-L1D-001',
                        'Keterangan' => 'Duplikat',
                        'Tanggal Cetak' => '2026-09-01',
                        // Kolom desa ada tapi kosong → rantai first-non-empty harus jatuh ke 'Desa'.
                        'Desa/Kelurahan/Kecamatan' => '',
                    ],
                    'row_number' => 2,
                ]);
            }
        );
        $l3Row = $l3->importData()->first();

        $report = Report::factory()->for($user)->create([
            'import_id' => $pn->id,
            'config_json' => [
                'fields' => ['Kelurahan', 'Jumlah Nikah', 'Duplikat'],
                'filter_month' => '9',
                'filter_year' => '2026',
                'merged_import_ids' => [$pn->id],
                'table_layout' => ['type' => 'laporan_l1'],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('reports.print', $report));
        $response->assertOk();
        $sonowangi = collect($response->viewData('dataset')['rows'])->firstWhere('Kelurahan', 'SONOWANGI');
        $this->assertNotNull($sonowangi);
        $this->assertSame(0, $sonowangi['Duplikat']);

        Livewire::actingAs($user)
            ->test('data-table', [
                'importIds' => [$l3->id],
                'filterColumn' => 'Keterangan',
                'filterValue' => 'Duplikat',
                'filterMode' => 'exact',
                'editableColumns' => ['Desa'],
            ])
            ->call('updateCell', $l3Row->id, 'Desa', 'sonowangi');

        $response = $this->actingAs($user)->get(route('reports.print', $report));
        $response->assertOk();
        $sonowangi = collect($response->viewData('dataset')['rows'])->firstWhere('Kelurahan', 'SONOWANGI');
        $this->assertSame(1, $sonowangi['Duplikat']);
    }

    private function laporanNaReportWithPerforasi(User $user, array $nums, array $rusakStatusNums = []): Report
    {
        $import = Import::factory()->for($user)->create([
            'status' => 'success',
            'table_name' => 'laporan model l3',
        ]);

        foreach ($nums as $i => $num) {
            $perforasi = '116741'.str_pad((string) $num, 3, '0', STR_PAD_LEFT);
            ImportData::factory()->for($import)->create([
                'row_data' => [
                    'Nomor Perforasi' => $perforasi,
                    'Keterangan' => in_array($num, $rusakStatusNums, true) ? 'Rusak' : 'Bukan Duplikat',
                    'Nama Catin' => 'Suami '.$num.' - Istri '.$num,
                    'Tanggal Akad' => '2026-09-10',
                    'Tanggal Nikah' => '2026-09-10',
                    'Tanggal Cetak' => '2026-09-10',
                ],
                'row_number' => $i + 2,
            ]);
        }

        return Report::factory()->for($user)->create([
            'import_id' => $import->id,
            'output_format' => 'print',
            'config_json' => [
                'fields' => ['Tanggal Akad', 'Keterangan', 'Nama Catin', 'Nomor Perforasi', 'Tanggal Nikah'],
                'is_merged' => true,
                'merged_import_ids' => [$import->id],
                'filter_month' => '9',
                'filter_year' => '2026',
                'sort_column' => 'Tanggal Nikah',
                'sort_direction' => 'asc',
                'table_layout' => ['type' => 'laporan_na'],
                'manual_data' => [
                    'sisa_bulan_lalu' => [
                        'uraian' => 'Sisa bulan lalu',
                        'masuk' => '6',
                        'keluar' => '',
                        'seri_dari' => '116741001',
                        'seri_sampai' => '116741006',
                        'model' => 'NA',
                    ],
                ],
            ],
        ]);
    }

    /** @return array<int, array<int, string>> */
    private function laporanL3ReportWithPerforasi(User $user, array $nums, int $masuk): Report
    {
        $import = Import::factory()->for($user)->create([
            'status' => 'success',
            'table_name' => 'laporan model l3',
        ]);

        foreach ($nums as $i => $num) {
            $perforasi = '116741'.str_pad((string) $num, 3, '0', STR_PAD_LEFT);
            ImportData::factory()->for($import)->create([
                'row_data' => [
                    'Nomor Perforasi' => $perforasi,
                    'Keterangan' => 'Bukan Duplikat',
                    'Nama Catin' => 'Suami '.$num.' - Istri '.$num,
                    'Tanggal Akad' => '2026-09-10',
                    'Tanggal Nikah' => '2026-09-10',
                    'Tanggal Cetak' => '2026-09-10',
                ],
                'row_number' => $i + 2,
            ]);
        }

        $masukAkhir = '116741'.str_pad((string) $masuk, 3, '0', STR_PAD_LEFT);
        $staticRows = [
            ['formulir' => 'Model N', 'row_num' => 1],
            ['formulir' => 'Model NA (116741)', 'row_num' => 2],
            ['formulir' => 'Model DN', 'row_num' => 3],
            ['formulir' => 'Model NB', 'row_num' => 4],
        ];

        return Report::factory()->for($user)->create([
            'import_id' => $import->id,
            'output_format' => 'print',
            'config_json' => [
                'fields' => ['Nomor Perforasi', 'Keterangan', 'Nama Catin', 'Tanggal Akad', 'Tanggal Cetak'],
                'is_merged' => true,
                'merged_import_ids' => [$import->id],
                'filter_month' => '9',
                'filter_year' => '2026',
                'sort_column' => 'Tanggal Cetak',
                'sort_direction' => 'asc',
                'table_layout' => [
                    'type' => 'formulir',
                    'static_rows' => $staticRows,
                ],
                'manual_data' => [
                    'rows' => [
                        ['formulir' => 'Model N', 'masuk_jumlah' => '', 'keluar_jumlah' => '', 'sisa_jumlah' => '', 'keterangan' => ''],
                        [
                            'formulir' => 'Model NA (116741)',
                            'masuk_jumlah' => (string) $masuk,
                            'masuk_seri_awal' => '116741001',
                            'masuk_seri_akhir' => $masukAkhir,
                            'masuk_seri' => 'JT 116741001 - '.$masukAkhir,
                            'keluar_jumlah' => '',
                            'keluar_seri' => '',
                            'sisa_jumlah' => '',
                            'sisa_seri' => '',
                            'keterangan' => '',
                        ],
                        ['formulir' => 'Model DN', 'masuk_jumlah' => '', 'keluar_jumlah' => '', 'sisa_jumlah' => '', 'keterangan' => ''],
                        ['formulir' => 'Model NB', 'masuk_jumlah' => '', 'keluar_jumlah' => '', 'sisa_jumlah' => '', 'keterangan' => ''],
                    ],
                    'static_rows' => $staticRows,
                ],
            ],
        ]);
    }

    private function tableCellRows(string $html): array
    {
        $rows = [];
        if (! preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/s', $html, $trs)) {
            return $rows;
        }
        foreach ($trs[1] as $tr) {
            if (! preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $tr, $cells)) {
                continue;
            }
            $rows[] = array_map(fn ($c) => trim(html_entity_decode(strip_tags($c))), $cells[1]);
        }

        return $rows;
    }

    public function test_laporan_na_menampilkan_baris_rusak_dari_perforasi_hilang(): void
    {
        $user = User::factory()->create();
        // Nomor 003 hilang (rusak) di tengah rentang; 006 belum terpakai (stok, bukan rusak).
        $report = $this->laporanNaReportWithPerforasi($user, [1, 2, 4, 5]);

        $response = $this->actingAs($user)->get(route('reports.print', $report));
        $response->assertOk();
        $response->assertSee('Rusak');
        $response->assertSee('JT 116741003');

        $rows = $this->tableCellRows($response->getContent());

        $rusak = collect($rows)->first(fn ($r) => ($r[2] ?? '') === 'Rusak');
        $this->assertNotNull($rusak, 'Baris Rusak harus tampil di laporan NA');
        $this->assertSame('10/09/2026', $rusak[1]);
        $this->assertSame('', $rusak[3], 'Masuk baris Rusak kosong');
        $this->assertSame('1', $rusak[4], 'Rusak dihitung sebagai Keluar');
        $this->assertSame('1', $rusak[5], 'Sisa dikurangi rusak: 6 - 4 terpakai - 1 rusak');
        $this->assertSame('NA', $rusak[6]);
        $this->assertSame('JT 116741003', $rusak[7], 'Hanya nomor hilang yang dijadikan seri (006 bukan rusak)');
        $this->assertSame('Buku', $rusak[8]);

        $jumlah = collect($rows)->first(fn ($r) => ($r[2] ?? '') === 'Jumlah');
        $this->assertNotNull($jumlah);
        $this->assertSame('6', $jumlah[3], 'Masuk = sisa bulan lalu');
        $this->assertSame('5', $jumlah[4], 'Keluar = 4 baris + 1 rusak');
        $this->assertSame('1', $jumlah[5], 'Sisa = 6 - 5');
    }

    public function test_laporan_na_tanpa_celah_tidak_menampilkan_rusak(): void
    {
        $user = User::factory()->create();
        // Rentang kontigu: tidak ada nomor hilang → tanpa baris Rusak.
        $report = $this->laporanNaReportWithPerforasi($user, [1, 2, 3, 4]);

        $response = $this->actingAs($user)->get(route('reports.print', $report));
        $response->assertOk();
        $response->assertDontSee('Rusak');

        $jumlah = collect($this->tableCellRows($response->getContent()))->first(fn ($r) => ($r[2] ?? '') === 'Jumlah');
        $this->assertNotNull($jumlah);
        $this->assertSame('4', $jumlah[4], 'Keluar = 4 baris, tanpa rusak');
        $this->assertSame('2', $jumlah[5], 'Sisa = 6 - 4');
    }

    public function test_laporan_na_abaikan_celah_palsu_reprint_lintas_bulan(): void
    {
        $user = User::factory()->create();

        $import = Import::factory()->for($user)->create([
            'status' => 'success',
            'table_name' => 'laporan model l3',
        ]);

        $rows = [
            ['116741251', '2025-12-01'],
            ['116741252', '2025-12-02'],
            ['116741253', '2025-12-03'],
            ['116741254', '2025-12-04'],
            ['116741256', '2025-12-05'],
            ['116741257', '2025-12-06'],
            ['116741258', '2026-01-05'],
            ['116741259', '2026-01-06'],
            ['116741255', '2026-04-05'],
            ['116741260', '2026-04-10'],
            ['116741261', '2026-04-10'],
            ['116741263', '2026-04-10'],
        ];

        foreach ($rows as $i => [$perforasi, $tanggal]) {
            ImportData::factory()->for($import)->create([
                'row_data' => [
                    'Nomor Perforasi' => $perforasi,
                    'Keterangan' => 'Bukan Duplikat',
                    'Nama Catin' => 'Suami '.$perforasi.' - Istri '.$perforasi,
                    'Tanggal Akad' => $tanggal,
                    'Tanggal Nikah' => $tanggal,
                    'Tanggal Cetak' => $tanggal,
                ],
                'row_number' => $i + 2,
            ]);
        }

        $report = Report::factory()->for($user)->create([
            'import_id' => $import->id,
            'output_format' => 'print',
            'config_json' => [
                'fields' => ['Tanggal Akad', 'Keterangan', 'Nama Catin', 'Nomor Perforasi', 'Tanggal Nikah'],
                'is_merged' => true,
                'merged_import_ids' => [$import->id],
                'filter_month' => '4',
                'filter_year' => '2026',
                'sort_column' => 'Tanggal Nikah',
                'sort_direction' => 'asc',
                'table_layout' => ['type' => 'laporan_na'],
                'manual_data' => [
                    'sisa_bulan_lalu' => [
                        'uraian' => 'Sisa bulan lalu',
                        'masuk' => '12',
                        'keluar' => '',
                        'seri_dari' => '116741251',
                        'seri_sampai' => '116741263',
                        'model' => 'NA',
                    ],
                ],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('reports.print', $report));
        $response->assertOk();

        $dataset = $response->viewData('dataset');
        $this->assertSame(['116741' => [116741262]], $dataset['config_json']['manual_data']['rusak_by_prefix']);

        $rusak = collect($this->tableCellRows($response->getContent()))->first(fn ($r) => ($r[2] ?? '') === 'Rusak');
        $this->assertNotNull($rusak, 'Baris Rusak harus tampil di laporan NA');
        $this->assertSame('10/04/2026', $rusak[1]);
        $this->assertSame('1', $rusak[4], 'Rusak dihitung sebagai Keluar');
        $this->assertSame('JT 116741262', $rusak[7], 'Hanya celah nyata 262, bukan rentang palsu 256..263');
    }

    public function test_laporan_na_baris_status_rusak_dihitung_sebagai_rusak(): void
    {
        $user = User::factory()->create();
        // Rentang 001..005 lengkap (tanpa celah); nomor 003 ditandai Status RUSAK di L3.
        $report = $this->laporanNaReportWithPerforasi($user, [1, 2, 3, 4, 5], [3]);

        $response = $this->actingAs($user)->get(route('reports.print', $report));
        $response->assertOk();

        $rows = $this->tableCellRows($response->getContent());

        $rusak = collect($rows)->first(fn ($r) => ($r[2] ?? '') === 'Rusak');
        $this->assertNotNull($rusak, 'Baris Rusak harus tampil untuk Status RUSAK');
        $this->assertSame('10/09/2026', $rusak[1]);
        $this->assertSame('1', $rusak[4], 'Status RUSAK dihitung sebagai Keluar');
        $this->assertSame('1', $rusak[5], 'Sisa = 6 - 4 terpakai - 1 rusak');
        $this->assertSame('JT 116741003', $rusak[7], 'Nomor Status RUSAK tampil di kolom seri');

        $jumlah = collect($rows)->first(fn ($r) => ($r[2] ?? '') === 'Jumlah');
        $this->assertNotNull($jumlah);
        $this->assertSame('6', $jumlah[3], 'Masuk = sisa bulan lalu');
        $this->assertSame('5', $jumlah[4], 'Keluar = 5 baris (termasuk yang rusak)');
        $this->assertSame('1', $jumlah[5], 'Sisa = 6 - 5');
    }

    public function test_laporan_l3_rusak_masuk_hitungan_tanpa_label_baru(): void
    {
        $user = User::factory()->create();
        // Nomor 003 hilang (rusak) di tengah rentang → celah interior = 1 nomor.
        $report = $this->laporanL3ReportWithPerforasi($user, [1, 2, 4], 10);

        $response = $this->actingAs($user)->get(route('reports.print', $report));
        $response->assertOk();
        // Tabel L3 tidak boleh punya baris/kolom label "Rusak".
        $response->assertDontSee('Rusak');

        $rows = $this->tableCellRows($response->getContent());
        $na = collect($rows)->first(fn ($r) => ($r[1] ?? '') === 'Model NA (116741)');
        $this->assertNotNull($na, 'Baris Model NA harus tampil');
        $this->assertSame('10', $na[2], 'Masuk tetap 10');
        $this->assertSame('JT 116741001 - 116741010', $na[3], 'Masuk seri tidak berubah');
        $this->assertSame('4', $na[4], 'Keluar = 3 baris + 1 rusak');
        $this->assertSame('JT 116741001 - 116741004', $na[5], 'Keluar seri tetap min..max baris');
        $this->assertSame('6', $na[6], 'Sisa = 10 - 4');
        $this->assertSame('JT 116741005 - 116741010', $na[7], 'Sisa seri tetap ekor setelah nomor terakhir');
    }

    public function test_laporan_l3_tanpa_celah_angka_tetap(): void
    {
        $user = User::factory()->create();
        // Rentang kontigu: tidak ada rusak → angka sama persis seperti sebelum fitur.
        $report = $this->laporanL3ReportWithPerforasi($user, [1, 2, 3, 4], 10);

        $response = $this->actingAs($user)->get(route('reports.print', $report));
        $response->assertOk();
        $response->assertDontSee('Rusak');

        $rows = $this->tableCellRows($response->getContent());
        $na = collect($rows)->first(fn ($r) => ($r[1] ?? '') === 'Model NA (116741)');
        $this->assertNotNull($na);
        $this->assertSame('4', $na[4], 'Keluar = 4 baris tanpa tambahan rusak');
        $this->assertSame('6', $na[6], 'Sisa = 10 - 4');
    }

    public function test_laporan_l3_sisa_tidak_negatif_karena_rusak(): void
    {
        $user = User::factory()->create();
        // Masuk 3 dengan 3 baris + 1 rusak → keluar 4, sisa di-clamp 0 (bukan -1).
        $report = $this->laporanL3ReportWithPerforasi($user, [1, 2, 4], 3);

        $response = $this->actingAs($user)->get(route('reports.print', $report));
        $response->assertOk();

        $rows = $this->tableCellRows($response->getContent());
        $na = collect($rows)->first(fn ($r) => ($r[1] ?? '') === 'Model NA (116741)');
        $this->assertNotNull($na);
        $this->assertSame('4', $na[4], 'Keluar = 3 baris + 1 rusak');
        $this->assertSame('0', $na[6], 'Sisa di-clamp ke 0, tidak negatif');
    }

    private function reportStatus(Report $report): string
    {
        return $report->fresh()->status;
    }
}
