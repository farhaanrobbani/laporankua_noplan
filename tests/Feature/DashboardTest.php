<?php

namespace Tests\Feature;

use App\Models\Import;
use App\Models\ImportData;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_dialihkan_ke_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_user_melihat_statistik_dashboard(): void
    {
        $user = User::factory()->create();

        $import = Import::factory()->for($user)->create(['file_name' => 'data_karyawan.xlsx', 'table_name' => 'data karyawan']);
        ImportData::factory()->for($import)->count(3)->create();
        Report::factory()->for($user)->for($import)->create(['title' => 'Laporan Karyawan']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('data karyawan');
        $response->assertSee('Laporan Karyawan');
        $response->assertSee('Total File Import');
        $response->assertSee('Total Data Record');
        $response->assertSee('Laporan Dibuat');
    }

    public function test_data_user_lain_tidak_terlihat(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $importB = Import::factory()->for($userB)->create(['file_name' => 'rahasia_b.xlsx', 'table_name' => 'rahasia b']);
        Report::factory()->for($userB)->for($importB)->create(['title' => 'Laporan Rahasia B']);

        $response = $this->actingAs($userA)->get('/dashboard');

        $response->assertOk();
        $response->assertDontSee('rahasia b');
        $response->assertDontSee('Laporan Rahasia B');
    }

    public function test_empty_state_saat_belum_ada_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Belum ada data import');
        $response->assertSee('Belum ada laporan');
    }

    private function seedStatsFixtures(User $user): void
    {
        $mk = fn (string $tableName, array $rows) => tap(
            Import::factory()->for($user)->create([
                'status' => 'success',
                'table_name' => $tableName,
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

        $mk('laporan peristiwa nikah', [
            ['Nomor Daftar' => 'ND-1', 'Tanggal Nikah' => '15-09-2026', 'Penghulu' => 'MOHAMAD AMIN'],
            ['Nomor Daftar' => 'ND-2', 'Tanggal Nikah' => '10-10-2026'],
            ['Nomor Daftar' => 'ND-X', 'Tanggal Nikah' => '20-09-2026', 'Penghulu' => 'BUDI SAPUTRA'],
            ['Nomor Daftar' => 'ND-3', 'Tanggal Nikah' => 'bukan tanggal'],
            ['Nomor Daftar' => 'ND-4', 'Tanggal Nikah' => '25-09-2026', 'Penghulu' => 'MOHAMAD AMIN'],
        ]);

        $mk('laporan pendaftaran nikah', [
            ['Nomor Daftar' => 'ND-1', 'Tanggal Daftar' => '01-09-2026', 'Nikah Di' => 'KUA / KANTOR'],
            ['Nomor Daftar' => 'ND-2', 'Tanggal Daftar' => '05-10-2026', 'Nikah Di' => 'LUAR KUA / BEDOL'],
            ['Nomor Daftar' => 'ND-4', 'Tanggal Daftar' => '20-09-2026', 'Nikah Di' => 'LUAR KUA / BEDOL'],
        ]);

        $mk('laporan model l3', [
            ['Nomor Perforasi' => '111', 'Keterangan' => 'Duplikat', 'Tanggal Cetak' => '02-09-2026'],
            ['Nomor Perforasi' => '111', 'Keterangan' => 'Duplikat', 'Tanggal Cetak' => '02-09-2026'],
            ['Nomor Perforasi' => '222', 'Keterangan' => 'Duplikat', 'Tanggal Cetak' => '03-10-2026'],
            ['Nomor Perforasi' => '333', 'Keterangan' => 'Bukan Duplikat', 'Tanggal Cetak' => '04-09-2026'],
        ]);
    }

    public function test_statistik_periode_mengikuti_filter_bulan_tahun(): void
    {
        $user = User::factory()->create();
        $this->seedStatsFixtures($user);

        $september = $this->actingAs($user)->get('/dashboard?bulan=9&tahun=2026');
        $september->assertOk()->assertSee('Statistik Laporan')->assertSee('Peristiwa Nikah');

        $stats = $september->viewData('stats');
        $this->assertSame(9, $september->viewData('bulan'));
        $this->assertSame(2026, $september->viewData('tahun'));
        $this->assertSame('September', $september->viewData('monthName'));

        // Periode September: pn=3 (1 baris tanggal rusak diabaikan), pdk=2, duplikat=1 (dedup perforasi 111).
        $this->assertSame(3, $stats['pn_bulan']);
        $this->assertSame(2, $stats['pdk_bulan']);
        $this->assertSame(1, $stats['dup_bulan']);

        // Tahun 2026 tetap menghitung seluruh bulan untuk duplikat.
        $this->assertSame(2, $stats['dup_tahun']);

        $oktober = $this->actingAs($user)->get('/dashboard?bulan=10&tahun=2026');
        $oktober->assertOk();
        $stats = $oktober->viewData('stats');
        $this->assertSame(1, $stats['pn_bulan']);
        $this->assertSame(1, $stats['pdk_bulan']);
        $this->assertSame(1, $stats['dup_bulan']);
        $this->assertSame(2, $stats['dup_tahun']);

        $tanpaFilter = $this->actingAs($user)->get('/dashboard');
        $tanpaFilter->assertOk();
        $this->assertSame((int) now()->month, $tanpaFilter->viewData('bulan'));
        $this->assertSame((int) now()->year, $tanpaFilter->viewData('tahun'));
    }

    public function test_statistik_kantor_luar_ikut_periode_dan_join_pn(): void
    {
        $user = User::factory()->create();
        $this->seedStatsFixtures($user);

        // September: pn ND-1 join pdk (KUA/KANTOR) + ND-X tanpa pasangan -> kantor; ND-4 join pdk (LUAR) -> luar; pdk: 1 kantor + 1 luar.
        $stats = $this->actingAs($user)->get('/dashboard?bulan=9&tahun=2026')->viewData('stats');
        $this->assertSame(2, $stats['pn_kantor']);
        $this->assertSame(1, $stats['pn_luar']);
        $this->assertSame(1, $stats['pdk_kantor']);
        $this->assertSame(1, $stats['pdk_luar']);

        // Oktober: pn ND-2 join pdk (LUAR KUA/BEDOL) -> luar; pdk ND-2 luar.
        $stats = $this->actingAs($user)->get('/dashboard?bulan=10&tahun=2026')->viewData('stats');
        $this->assertSame(0, $stats['pn_kantor']);
        $this->assertSame(1, $stats['pn_luar']);
        $this->assertSame(0, $stats['pdk_kantor']);
        $this->assertSame(1, $stats['pdk_luar']);
    }

    public function test_statistik_tidak_menghitung_data_user_lain(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->seedStatsFixtures($userB);

        Import::factory()->for($userA)->create([
            'status' => 'success',
            'table_name' => 'laporan peristiwa nikah',
        ])->importData()->create([
            'row_data' => ['Nomor Daftar' => 'A-1', 'Tanggal Nikah' => '15-09-2026'],
            'row_number' => 2,
        ]);

        $stats = $this->actingAs($userA)->get('/dashboard?bulan=9&tahun=2026')->viewData('stats');

        $this->assertSame(1, $stats['pn_bulan']);
        $this->assertSame(0, $stats['pdk_bulan']);
        $this->assertSame(0, $stats['dup_bulan']);
        $this->assertSame(1, $stats['pn_kantor']);
        $this->assertSame(0, $stats['pdk_kantor']);
    }

    public function test_statistik_list_penghulu_bulanan(): void
    {
        $user = User::factory()->create();
        $this->seedStatsFixtures($user);

        // List penghulu periode bulan (jumlah desc, nama asc saat seri) + pecahan kantor/luar per nama.
        $stats = $this->actingAs($user)->get('/dashboard?bulan=9&tahun=2026')->viewData('stats');
        $this->assertSame([
            ['nama' => 'MOHAMAD AMIN', 'kantor' => 1, 'luar' => 1, 'jumlah' => 2],
            ['nama' => 'BUDI SAPUTRA', 'kantor' => 1, 'luar' => 0, 'jumlah' => 1],
        ], $stats['penghulu_bulan']);

        // Oktober: ND-2 tanpa Penghulu → list kosong.
        $stats = $this->actingAs($user)->get('/dashboard?bulan=10&tahun=2026')->viewData('stats');
        $this->assertSame([], $stats['penghulu_bulan']);
    }

    private function mkImport(User $user, string $tableName, array $rows): Import
    {
        $import = Import::factory()->for($user)->create([
            'status' => 'success',
            'table_name' => $tableName,
        ]);

        foreach ($rows as $i => $row) {
            ImportData::factory()->for($import)->create([
                'row_data' => $row,
                'row_number' => $i + 2,
            ]);
        }

        return $import;
    }

    public function test_statistik_pn_tidak_menghitung_nomor_daftar_kembar(): void
    {
        $user = User::factory()->create();

        // Import kedua mengulang ND-1 (kasus upload ganda) — tidak boleh dihitung dua kali.
        $this->mkImport($user, 'laporan peristiwa nikah', [
            ['Nomor Daftar' => 'ND-1', 'Tanggal Nikah' => '15-09-2026'],
        ]);
        $this->mkImport($user, 'laporan peristiwa nikah', [
            ['Nomor Daftar' => 'ND-1', 'Tanggal Nikah' => '15-09-2026'],
            ['Nomor Daftar' => 'ND-2', 'Tanggal Nikah' => '20-09-2026'],
        ]);

        $stats = $this->actingAs($user)->get('/dashboard?bulan=9&tahun=2026')->viewData('stats');

        $this->assertSame(2, $stats['pn_bulan']);
    }

    public function test_statistik_pn_menggunakan_data_upload_terbaru(): void
    {
        $user = User::factory()->create();

        $this->mkImport($user, 'laporan peristiwa nikah', [
            ['Nomor Daftar' => 'ND-9', 'Tanggal Nikah' => '20-09-2026'],
        ]);
        $this->mkImport($user, 'laporan peristiwa nikah', [
            ['Nomor Daftar' => 'ND-9', 'Tanggal Nikah' => '10-10-2026'],
        ]);

        $september = $this->actingAs($user)->get('/dashboard?bulan=9&tahun=2026')->viewData('stats');
        $this->assertSame(0, $september['pn_bulan']);

        $oktober = $this->actingAs($user)->get('/dashboard?bulan=10&tahun=2026')->viewData('stats');
        $this->assertSame(1, $oktober['pn_bulan']);
    }

    public function test_statistik_pdk_tidak_menghitung_nomor_daftar_kembar(): void
    {
        $user = User::factory()->create();

        $this->mkImport($user, 'laporan pendaftaran nikah', [
            ['Nomor Daftar' => 'ND-1', 'Tanggal Daftar' => '01-09-2026', 'Nikah Di' => 'KUA / KANTOR'],
        ]);
        $this->mkImport($user, 'laporan pendaftaran nikah', [
            ['Nomor Daftar' => 'ND-1', 'Tanggal Daftar' => '01-09-2026', 'Nikah Di' => 'KUA / KANTOR'],
            ['Nomor Daftar' => 'ND-2', 'Tanggal Daftar' => '03-09-2026', 'Nikah Di' => 'LUAR KUA / BEDOL'],
        ]);

        $stats = $this->actingAs($user)->get('/dashboard?bulan=9&tahun=2026')->viewData('stats');

        $this->assertSame(2, $stats['pdk_bulan']);
        $this->assertSame(1, $stats['pdk_kantor']);
        $this->assertSame(1, $stats['pdk_luar']);
    }

    public function test_statistik_buku_rusak_status_dan_celah(): void
    {
        $user = User::factory()->create();

        $this->mkImport($user, 'laporan model l3', [
            // September: celah 002 (001 & 003 ada) + Status RUSAK (baris kembar dihitung sekali).
            ['Nomor Perforasi' => '116741001', 'Keterangan' => 'Bukan Duplikat', 'Tanggal Cetak' => '05-09-2026'],
            ['Nomor Perforasi' => '116741003', 'Keterangan' => 'Bukan Duplikat', 'Tanggal Cetak' => '05-09-2026'],
            ['Nomor Perforasi' => '116742001', 'Keterangan' => 'Rusak', 'Tanggal Cetak' => '06-09-2026'],
            ['Nomor Perforasi' => '116742001', 'Keterangan' => 'Rusak', 'Tanggal Cetak' => '06-09-2026'],
            // Oktober: Status RUSAK.
            ['Nomor Perforasi' => '116743001', 'Keterangan' => 'Rusak', 'Tanggal Cetak' => '03-10-2026'],
        ]);

        $september = $this->actingAs($user)->get('/dashboard?bulan=9&tahun=2026');
        $september->assertOk()->assertSee('Buku Rusak');
        $stats = $september->viewData('stats');
        $this->assertSame(2, $stats['rusak_bulan'], 'September: celah 002 + Status RUSAK');
        $this->assertSame(3, $stats['rusak_tahun'], 'Tahun = jumlah 12 bulan');

        $stats = $this->actingAs($user)->get('/dashboard?bulan=10&tahun=2026')->viewData('stats');
        $this->assertSame(1, $stats['rusak_bulan']);
        $this->assertSame(3, $stats['rusak_tahun']);
    }

    public function test_statistik_buku_rusak_abaikan_celah_palsu_reprint_lintas_bulan(): void
    {
        $user = User::factory()->create();

        $this->mkImport($user, 'laporan model l3', [
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

        $april = $this->actingAs($user)->get('/dashboard?bulan=4&tahun=2026')->viewData('stats');
        $this->assertSame(1, $april['rusak_bulan'], 'April: hanya celah 262');
        $this->assertSame(1, $april['rusak_tahun']);

        $desember = $this->actingAs($user)->get('/dashboard?bulan=12&tahun=2025')->viewData('stats');
        $this->assertSame(0, $desember['rusak_bulan'], '255 ada sebagai cetak ulang April');
        $this->assertSame(0, $desember['rusak_tahun']);
    }

    public function test_statistik_buku_rusak_isolasi_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->mkImport($userB, 'laporan model l3', [
            ['Nomor Perforasi' => '116741001', 'Keterangan' => 'Bukan Duplikat', 'Tanggal Cetak' => '05-09-2026'],
            ['Nomor Perforasi' => '116741003', 'Keterangan' => 'Bukan Duplikat', 'Tanggal Cetak' => '05-09-2026'],
            ['Nomor Perforasi' => '116742001', 'Keterangan' => 'Rusak', 'Tanggal Cetak' => '06-09-2026'],
        ]);

        $stats = $this->actingAs($userA)->get('/dashboard?bulan=9&tahun=2026')->viewData('stats');

        $this->assertSame(0, $stats['rusak_bulan']);
        $this->assertSame(0, $stats['rusak_tahun']);
    }
}
