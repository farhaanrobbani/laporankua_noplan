<?php

namespace Tests\Feature;

use App\Models\Import;
use App\Models\ImportData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtomasiTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    private function simponiImport(User $user, array $rows, string $status = 'success'): Import
    {
        $import = Import::factory()->for($user)->create([
            'status' => $status,
            'table_name' => 'laporan simponi',
        ]);

        foreach ($rows as $i => $row) {
            ImportData::factory()->for($import)->create([
                'row_data' => $row,
                'row_number' => $i + 2,
            ]);
        }

        return $import;
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

    public function test_guest_is_redirected_from_otomasi_pages(): void
    {
        foreach (['/otomasi/bimwin', '/otomasi/bukti-bayar', '/otomasi/surat-tugas'] as $uri) {
            $this->get($uri)->assertRedirect('/login');
        }
    }

    public function test_old_urls_redirect_to_otomasi(): void
    {
        $user = $this->user();

        $this->actingAs($user)->get('/bimwin')->assertRedirect('/otomasi/bimwin');
        $this->actingAs($user)->get('/data/bukti-bayar')->assertRedirect('/otomasi/bukti-bayar');
        $this->actingAs($user)->get('/data/surat-tugas')->assertRedirect('/otomasi/surat-tugas');
    }

    public function test_old_userscript_urls_redirect_to_combined_script(): void
    {
        $this->get('/userscripts/simkah4-bimwin.user.js')
            ->assertRedirect('/userscripts/simkah4-otomasi.user.js');
        $this->get('/userscripts/simkah4-bukti-bayar.user.js')
            ->assertRedirect('/userscripts/simkah4-otomasi.user.js');
    }

    public function test_user_sees_otomasi_tabs(): void
    {
        $user = $this->user();

        $this->actingAs($user)->get('/otomasi/bukti-bayar')
            ->assertOk()
            ->assertSee('Otomasi')
            ->assertSee('BIMWIN')
            ->assertSee('Bukti Bayar')
            ->assertSee('Surat Tugas')
            ->assertSee(route('otomasi.bimwin'), false)
            ->assertSee(route('otomasi.bukti-bayar'), false)
            ->assertSee(route('otomasi.surat-tugas'), false);
    }

    public function test_tab_bukti_bayar_tanpa_pdk_tampil_empty_state(): void
    {
        $user = $this->user();

        $response = $this->actingAs($user)->get('/otomasi/bukti-bayar');

        $response->assertOk();
        $response->assertSee('Belum ada data');
        $response->assertSee('pendaftaran nikah');
    }

    public function test_tab_bukti_bayar_menampilkan_simponi_milik_user_saja(): void
    {
        $userA = $this->user();
        $userB = $this->user();

        $this->pdkLuarImport($userA, [
            ['Nomor Daftar' => 'ND-A-001', 'Tanggal Nikah' => '08-09-2026'],
        ]);
        $this->simponiImport($userA, [
            [
                'No Billing' => '820260908847263',
                'Tanggal Billing' => '2026-09-08',
                'Nama Wajib Bayar' => 'Ahmad Fauzi',
                'Nominal Setor' => '600000',
                'No Pendaftaran' => 'ND-A-001',
                'No NTB' => 'NTB-A-001',
            ],
        ]);
        $this->pdkLuarImport($userB, [
            ['Nomor Daftar' => 'ND-B-001', 'Tanggal Nikah' => '09-09-2026'],
        ]);
        $this->simponiImport($userB, [
            [
                'No Billing' => '820260999000111',
                'Tanggal Billing' => '2026-09-09',
                'Nama Wajib Bayar' => 'Budi Rahasia',
                'No Pendaftaran' => 'ND-B-001',
            ],
        ]);

        $this->actingAs($userA)->get('/otomasi/bukti-bayar')
            ->assertOk()
            ->assertSee('820260908847263')
            ->assertSee('Ahmad Fauzi')
            ->assertSee('NTB-A-001')
            ->assertSee('08-09-2026')
            ->assertDontSee('820260999000111')
            ->assertDontSee('Budi Rahasia');
    }

    public function test_hanya_pelaksanaan_luar_kantor_yang_tampil(): void
    {
        $user = $this->user();
        $this->pdkLuarImport($user, [
            ['Nomor Daftar' => 'ND-LUAR-1', 'Tanggal Nikah' => '10-09-2026', 'Nikah Di' => 'LUAR KUA / BEDOL'],
            ['Nomor Daftar' => 'ND-KANTOR-1', 'Tanggal Nikah' => '10-09-2026', 'Nikah Di' => 'KUA / KANTOR'],
        ]);
        $this->simponiImport($user, [
            ['No Billing' => '820260910000001', 'Tanggal Billing' => '2026-08-20', 'Nama Wajib Bayar' => 'Warga Luar', 'No Pendaftaran' => 'ND-LUAR-1'],
            ['No Billing' => '820260910000002', 'Tanggal Billing' => '2026-08-20', 'Nama Wajib Bayar' => 'Warga Kantor', 'No Pendaftaran' => 'ND-KANTOR-1'],
            ['No Billing' => '820260910000003', 'Tanggal Billing' => '2026-08-20', 'Nama Wajib Bayar' => 'Warga Tanpa Padanan', 'No Pendaftaran' => 'ND-TIDAK-ADA'],
        ]);

        $this->actingAs($user)->get('/otomasi/bukti-bayar')
            ->assertOk()
            ->assertSee('820260910000001')
            ->assertSee('Warga Luar')
            ->assertDontSee('820260910000002')
            ->assertDontSee('Warga Kantor')
            ->assertDontSee('820260910000003')
            ->assertDontSee('Warga Tanpa Padanan')
            ->assertSee('2</strong> baris simponi tidak ditampilkan', false);
    }

    public function test_filter_bulan_mengikuti_tanggal_pelaksanaan_bukan_tanggal_billing(): void
    {
        $user = $this->user();
        $this->pdkLuarImport($user, [
            ['Nomor Daftar' => 'ND-AGT', 'Tanggal Nikah' => '30-09-2026'],
            ['Nomor Daftar' => 'ND-SEP', 'Tanggal Nikah' => '2026-09-15'],
        ]);
        $this->simponiImport($user, [
            ['No Billing' => '820260815000001', 'Tanggal Billing' => '2026-08-15', 'Nama Wajib Bayar' => 'Warga Billing Agustus', 'No Pendaftaran' => 'ND-AGT'],
            ['No Billing' => '820260915000002', 'Tanggal Billing' => '2026-09-15', 'Nama Wajib Bayar' => 'Warga Akad September', 'No Pendaftaran' => 'ND-SEP'],
        ]);

        $this->actingAs($user)->get('/otomasi/bukti-bayar?bulan=9&tahun=2026')
            ->assertOk()
            ->assertSee('820260815000001')
            ->assertSee('Warga Billing Agustus')
            ->assertSee('820260915000002')
            ->assertSee('30-09-2026');

        $this->actingAs($user)->get('/otomasi/bukti-bayar?bulan=8&tahun=2026')
            ->assertOk()
            ->assertDontSee('820260815000001')
            ->assertDontSee('Warga Billing Agustus')
            ->assertSee('Tidak ada data');
    }

    public function test_pencarian_bukti_bayar_memfilter_nama_dan_no_billing(): void
    {
        $user = $this->user();
        $this->pdkLuarImport($user, [
            ['Nomor Daftar' => 'ND-1', 'Tanggal Nikah' => '01-08-2026'],
            ['Nomor Daftar' => 'ND-2', 'Tanggal Nikah' => '01-09-2026'],
        ]);
        $this->simponiImport($user, [
            ['No Billing' => '820260801000001', 'Tanggal Billing' => '2026-08-01', 'Nama Wajib Bayar' => 'Ahmad Fauzi', 'No Pendaftaran' => 'ND-1'],
            ['No Billing' => '820260901000002', 'Tanggal Billing' => '2026-09-01', 'Nama Wajib Bayar' => 'Siti Aminah', 'No Pendaftaran' => 'ND-2'],
        ]);

        $this->actingAs($user)->get('/otomasi/bukti-bayar?q=ahmad')
            ->assertOk()
            ->assertSee('Ahmad Fauzi')
            ->assertDontSee('Siti Aminah');

        $this->actingAs($user)->get('/otomasi/bukti-bayar?q=820260901000002')
            ->assertOk()
            ->assertSee('Siti Aminah')
            ->assertDontSee('Ahmad Fauzi');
    }

    public function test_daftar_salin_memuat_no_billing_dan_nama_unik(): void
    {
        $user = $this->user();
        $this->pdkLuarImport($user, [
            ['Nomor Daftar' => 'ND-AGT', 'Tanggal Nikah' => '15-08-2026'],
            ['Nomor Daftar' => 'ND-SEP', 'Tanggal Nikah' => '01-09-2026'],
        ]);
        $this->simponiImport($user, [
            ['No Billing' => '820260901000002', 'Tanggal Billing' => '2026-09-01', 'Nama Wajib Bayar' => 'Warga September', 'No Pendaftaran' => 'ND-SEP'],
            ['No Billing' => '820260801000001', 'Tanggal Billing' => '2026-08-01', 'Nama Wajib Bayar' => 'Warga Agustus', 'No Pendaftaran' => 'ND-AGT'],
            ['No Billing' => '820260801000001', 'Tanggal Billing' => '2026-08-01', 'Nama Wajib Bayar' => 'Warga Agustus', 'No Pendaftaran' => 'ND-AGT'],
        ]);

        $this->actingAs($user)->get('/otomasi/bukti-bayar')
            ->assertOk()
            ->assertSee('id="salin-btn"', false)
            ->assertSee('Salin Daftar No Billing')
            ->assertSee('id="salin-fallback"', false)
            ->assertViewHas('daftarSalin', fn (array $daftar) => $daftar === [
                '820260801000001 - Warga Agustus',
                '820260901000002 - Warga September',
            ]);
    }

    public function test_bukti_bayar_dan_surat_tugas_memakai_10_baris_per_halaman(): void
    {
        $user = $this->user();
        $this->pdkLuarImport($user, [
            ['Nomor Daftar' => 'ND-1', 'Tanggal Nikah' => '01-09-2026'],
        ]);
        $this->simponiImport($user, [
            ['No Billing' => '820260901000001', 'Tanggal Billing' => '2026-09-01', 'Nama Wajib Bayar' => 'Ahmad Fauzi', 'No Pendaftaran' => 'ND-1'],
        ]);

        $this->actingAs($user)->get('/otomasi/bukti-bayar')
            ->assertOk()
            ->assertViewHas('billings', fn ($paginator) => $paginator->perPage() === 10);

        $this->actingAs($user)->get('/otomasi/surat-tugas')
            ->assertOk()
            ->assertViewHas('rows', fn ($paginator) => $paginator->perPage() === 10);
    }

    public function test_tab_surat_tugas_tanpa_pdk_tampil_empty_state(): void
    {
        $user = $this->user();

        $this->actingAs($user)->get('/otomasi/surat-tugas')
            ->assertOk()
            ->assertSee('Belum ada data')
            ->assertSee('pendaftaran nikah');
    }

    public function test_surat_tugas_menampilkan_semua_pelaksanaan_luar_kantor_termasuk_tanpa_billing(): void
    {
        $user = $this->user();
        $this->pdkLuarImport($user, [
            ['Nomor Daftar' => 'ND-1', 'Tanggal Nikah' => '08-09-2026', 'Nama Suami' => 'Budi Santoso', 'Nama Istri' => 'Siti Aminah'],
            ['Nomor Daftar' => 'ND-2', 'Tanggal Nikah' => '09-09-2026', 'Nama Suami' => 'Agus Salim', 'Nama Istri' => 'Aminah'],
        ]);

        $this->actingAs($user)->get('/otomasi/surat-tugas')
            ->assertOk()
            ->assertSee('ND-1')
            ->assertSee('Budi Santoso')
            ->assertSee('Siti Aminah')
            ->assertSee('ND-2')
            ->assertSee('Agus Salim')
            ->assertViewHas('totalRows', 2);
    }

    public function test_surat_tugas_filter_bulan_mengikuti_tanggal_nikah(): void
    {
        $user = $this->user();
        $this->pdkLuarImport($user, [
            ['Nomor Daftar' => 'ND-AGT', 'Tanggal Nikah' => '15-08-2026', 'Nama Suami' => 'Agus', 'Nama Istri' => 'Aminah'],
            ['Nomor Daftar' => 'ND-SEP', 'Tanggal Nikah' => '01-09-2026', 'Nama Suami' => 'Budi', 'Nama Istri' => 'Siti'],
        ]);

        $this->actingAs($user)->get('/otomasi/surat-tugas?bulan=8&tahun=2026')
            ->assertOk()
            ->assertSee('ND-AGT')
            ->assertSee('Agus')
            ->assertDontSee('ND-SEP')
            ->assertDontSee('Budi');

        $this->actingAs($user)->get('/otomasi/surat-tugas?bulan=9&tahun=2026')
            ->assertOk()
            ->assertSee('ND-SEP')
            ->assertDontSee('ND-AGT');
    }

    public function test_surat_tugas_daftar_salin_format_nomor_dan_nama_suami_dan_istri(): void
    {
        $user = $this->user();
        $this->pdkLuarImport($user, [
            ['Nomor Daftar' => 'ND-1', 'Tanggal Nikah' => '08-09-2026', 'Nama Suami' => 'Budi Santoso', 'Nama Istri' => 'Siti Aminah'],
            ['Nomor Daftar' => 'ND-2', 'Tanggal Nikah' => '09-09-2026', 'Nama Suami' => 'Agus Salim', 'Nama Istri' => 'Aminah Putri'],
        ]);

        $this->actingAs($user)->get('/otomasi/surat-tugas')
            ->assertOk()
            ->assertSee('id="salin-btn"', false)
            ->assertSee('Salin Daftar Nomor Daftar')
            ->assertViewHas('daftarSalin', fn (array $daftar) => $daftar === [
                'ND-1 - Budi Santoso dan Siti Aminah',
                'ND-2 - Agus Salim dan Aminah Putri',
            ]);
    }

    public function test_surat_tugas_isolasi_milik_user_saja(): void
    {
        $userA = $this->user();
        $userB = $this->user();
        $this->pdkLuarImport($userA, [
            ['Nomor Daftar' => 'ND-A', 'Tanggal Nikah' => '08-09-2026', 'Nama Suami' => 'Ahmad', 'Nama Istri' => 'Aminah'],
        ]);
        $this->pdkLuarImport($userB, [
            ['Nomor Daftar' => 'ND-B', 'Tanggal Nikah' => '08-09-2026', 'Nama Suami' => 'Bagus', 'Nama Istri' => 'Bella'],
        ]);

        $this->actingAs($userA)->get('/otomasi/surat-tugas')
            ->assertOk()
            ->assertSee('ND-A')
            ->assertDontSee('ND-B');

        $this->actingAs($userB)->get('/otomasi/surat-tugas')
            ->assertOk()
            ->assertSee('ND-B')
            ->assertDontSee('ND-A');
    }
}
