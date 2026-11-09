<?php

namespace Tests\Feature;

use App\Models\Import;
use App\Models\ImportData;
use App\Models\User;
use App\Services\L3ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class L3ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private function l3Import(User $user, string $fileName, array $rows, string $status = 'success'): Import
    {
        $import = Import::factory()->for($user)->create([
            'file_name' => $fileName,
            'table_name' => 'laporan model l3',
            'status' => $status,
            'dedup_column' => 'Nomor Perforasi',
        ]);

        foreach ($rows as $i => $row) {
            ImportData::factory()->for($import)->create([
                'row_data' => $row,
                'row_number' => $i + 2,
                'dedup_key_value' => $row['Nomor Perforasi'] ?? null,
            ]);
        }

        return $import;
    }

    private function rows(Import $import): array
    {
        return $import->importData()
            ->orderBy('row_number')
            ->get()
            ->mapWithKeys(fn (ImportData $row) => [$row->row_data['Nomor Perforasi'] => $row->row_data])
            ->all();
    }

    public function test_akad_bulan_sebelumnya_dalam_toleransi_bukan_duplikat(): void
    {
        $user = User::factory()->create();
        $import = $this->l3Import($user, 'laporan-model-l3-06-2026.xlsx', [
            ['Nomor Perforasi' => 'PF-001', 'Nama Catin' => 'A - B', 'Tanggal Akad' => '30-05-2026'],
            ['Nomor Perforasi' => 'PF-002', 'Nama Catin' => 'A - B', 'Tanggal Akad' => '30-05-2026'],
            ['Nomor Perforasi' => 'PF-003', 'Nama Catin' => 'C - D', 'Tanggal Akad' => '31-05-2026'],
        ]);

        $stats = app(L3ReportService::class)->reprocessUser($user->id);
        $rows = $this->rows($import);

        foreach (['PF-001', 'PF-002', 'PF-003'] as $nomor) {
            $this->assertSame('Bukan Duplikat', $rows[$nomor]['Keterangan']);
            $this->assertSame('2026-06-01', $rows[$nomor]['Tanggal Cetak']);
        }

        $this->assertSame(0, $stats['duplicates']);
        $this->assertSame(3, $stats['updated']);
    }

    public function test_akad_bulan_berikutnya_dalam_toleransi_bukan_duplikat(): void
    {
        $user = User::factory()->create();
        $import = $this->l3Import($user, 'laporan-model-l3-05-2026.xlsx', [
            ['Nomor Perforasi' => 'PF-001', 'Nama Catin' => 'A - B', 'Tanggal Akad' => '01-06-2026'],
        ]);

        app(L3ReportService::class)->reprocessUser($user->id);
        $row = $this->rows($import)['PF-001'];

        $this->assertSame('Bukan Duplikat', $row['Keterangan']);
        $this->assertSame('01-06-2026', $row['Tanggal Cetak']);
    }

    public function test_event_sama_di_import_bulan_berikutnya_duplikat(): void
    {
        $user = User::factory()->create();
        $may = $this->l3Import($user, 'laporan-model-l3-05-2026.xlsx', [
            ['Nomor Perforasi' => 'PF-MAY', 'Nama Catin' => 'A - B', 'Tanggal Akad' => '20-05-2026'],
        ]);
        $june = $this->l3Import($user, 'laporan-model-l3-06-2026.xlsx', [
            ['Nomor Perforasi' => 'PF-JUNE-1', 'Nama Catin' => 'A - B', 'Tanggal Akad' => '20-05-2026'],
            ['Nomor Perforasi' => 'PF-JUNE-2', 'Nama Catin' => 'A - B', 'Tanggal Akad' => '20-05-2026'],
        ]);

        $stats = app(L3ReportService::class)->reprocessUser($user->id);
        $juneRows = $this->rows($june);

        $this->assertSame('Bukan Duplikat', $this->rows($may)['PF-MAY']['Keterangan']);

        foreach (['PF-JUNE-1', 'PF-JUNE-2'] as $nomor) {
            $this->assertSame('Duplikat', $juneRows[$nomor]['Keterangan']);
            $this->assertSame('2026-06-01', $juneRows[$nomor]['Tanggal Cetak']);
        }

        $this->assertSame(2, $stats['duplicates']);
    }

    public function test_event_sama_di_import_bulan_sebelumnya_duplikat(): void
    {
        $user = User::factory()->create();
        $may = $this->l3Import($user, 'laporan-model-l3-05-2026.xlsx', [
            ['Nomor Perforasi' => 'PF-MAY', 'Nama Catin' => 'A - B', 'Tanggal Akad' => '15-06-2026'],
        ]);
        $june = $this->l3Import($user, 'laporan-model-l3-06-2026.xlsx', [
            ['Nomor Perforasi' => 'PF-JUNE', 'Nama Catin' => 'A - B', 'Tanggal Akad' => '15-06-2026'],
        ]);

        app(L3ReportService::class)->reprocessUser($user->id);

        $this->assertSame('Bukan Duplikat', $this->rows($may)['PF-MAY']['Keterangan']);
        $this->assertSame('Duplikat', $this->rows($june)['PF-JUNE']['Keterangan']);
    }

    public function test_urutan_upload_tidak_mempengaruhi_event_pertama(): void
    {
        $user = User::factory()->create();
        $june = $this->l3Import($user, 'laporan-model-l3-06-2026.xlsx', [
            ['Nomor Perforasi' => 'PF-JUNE', 'Nama Catin' => 'A - B', 'Tanggal Akad' => '20-05-2026'],
        ]);
        $may = $this->l3Import($user, 'laporan-model-l3-05-2026.xlsx', [
            ['Nomor Perforasi' => 'PF-MAY', 'Nama Catin' => 'A - B', 'Tanggal Akad' => '20-05-2026'],
        ]);

        app(L3ReportService::class)->reprocessUser($user->id);

        $this->assertSame('Bukan Duplikat', $this->rows($may)['PF-MAY']['Keterangan']);
        $this->assertSame('Duplikat', $this->rows($june)['PF-JUNE']['Keterangan']);
    }

    public function test_nama_catin_dinormalisasi_saat_mencocokkan_event(): void
    {
        $user = User::factory()->create();
        $may = $this->l3Import($user, 'laporan-model-l3-05-2026.xlsx', [
            [
                'Nomor Perforasi' => 'PF-MAY',
                'Nama Catin' => 'MOCH. ROFIKI R - ALISYA MIA AUDINI',
                'Tanggal Akad' => '20-05-2026',
            ],
        ]);
        $june = $this->l3Import($user, 'laporan-model-l3-06-2026.xlsx', [
            [
                'Nomor Perforasi' => 'PF-JUNE',
                'Nama Catin' => '  moch rofiki r - alisya mia audini ',
                'Tanggal Akad' => '20-05-2026',
            ],
        ]);

        app(L3ReportService::class)->reprocessUser($user->id);

        $this->assertSame('Bukan Duplikat', $this->rows($may)['PF-MAY']['Keterangan']);
        $this->assertSame('Duplikat', $this->rows($june)['PF-JUNE']['Keterangan']);
    }

    public function test_tanggal_di_luar_toleransi_tetap_duplikat(): void
    {
        $user = User::factory()->create();
        $import = $this->l3Import($user, 'laporan-model-l3-06-2026.xlsx', [
            ['Nomor Perforasi' => 'PF-OLD', 'Nama Catin' => 'A - B', 'Tanggal Akad' => '11-08-1999'],
            ['Nomor Perforasi' => 'PF-PREV', 'Nama Catin' => 'C - D', 'Tanggal Akad' => '01-04-2026'],
            ['Nomor Perforasi' => 'PF-NEXT', 'Nama Catin' => 'E - F', 'Tanggal Akad' => '01-08-2026'],
        ]);

        app(L3ReportService::class)->reprocessUser($user->id);
        $rows = $this->rows($import);

        foreach (['PF-OLD', 'PF-PREV', 'PF-NEXT'] as $nomor) {
            $this->assertSame('Duplikat', $rows[$nomor]['Keterangan']);
            $this->assertSame('2026-06-01', $rows[$nomor]['Tanggal Cetak']);
        }
    }

    public function test_command_reprocess_mendukung_dry_run(): void
    {
        $user = User::factory()->create();
        $import = $this->l3Import($user, 'laporan-model-l3-06-2026.xlsx', [
            ['Nomor Perforasi' => 'PF-001', 'Nama Catin' => 'A - B', 'Tanggal Akad' => '11-08-1999'],
        ]);

        $this->artisan('l3:reprocess', ['--user' => $user->id, '--dry-run' => true])
            ->assertSuccessful();

        $this->assertArrayNotHasKey('Keterangan', $this->rows($import)['PF-001']);

        $this->artisan('l3:reprocess', ['--user' => $user->id])
            ->assertSuccessful();

        $this->assertSame('Duplikat', $this->rows($import)['PF-001']['Keterangan']);
    }
}
