<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtomasiPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_bimwin_page(): void
    {
        $this->get(route('otomasi.bimwin'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_see_bimwin_page_with_profile_config(): void
    {
        $user = $this->user([
            'provinsi' => 'Provinsi Uji',
            'kota_kabupaten' => 'Kabupaten Uji',
            'kecamatan' => 'Kecamatan Uji',
        ]);

        $this->actingAs($user)
            ->get(route('otomasi.bimwin'))
            ->assertOk()
            ->assertSee('Otomasi Pengisian BIMWIN')
            ->assertSee('Provinsi Uji')
            ->assertSee('Kabupaten Uji')
            ->assertSee('Kecamatan Uji')
            ->assertSee('simkah4-otomasi.user.js')
            ->assertSee('Cek saja')
            ->assertSee('Cara merekam otomatis')
            ->assertSee('Rekam endpoint')
            ->assertSee('kanan bawah')
            ->assertSee('Tidak ada wilayah bawaan di aplikasi.');
    }

    public function test_bimwin_page_warns_when_profile_region_is_incomplete(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->get(route('otomasi.bimwin'))
            ->assertOk()
            ->assertSee('Lengkapi provinsi, kabupaten/kota, dan kecamatan');
    }

    public function test_bimwin_navigation_link_is_visible(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('otomasi.bimwin'), false)
            ->assertSee('Otomasi');
    }

    public function test_otomasi_userscript_is_available(): void
    {
        $path = public_path('userscripts/simkah4-otomasi.user.js');

        $this->assertFileExists($path);

        $content = file_get_contents($path);

        $this->assertStringContainsString('SIMKAH4 — Otomasi KUA', $content);
        $this->assertStringContainsString('https://simkah4.kemenag.go.id/*', $content);
        $this->assertMatchesRegularExpression('/@version\s+3\.0\.0/', $content);
        $this->assertMatchesRegularExpression('/@grant\s+unsafeWindow/', $content);
        $this->assertStringContainsString('recordingPageWindow.fetch', $content);
        $this->assertStringContainsString('recordingPageWindow.XMLHttpRequest', $content);
        $this->assertStringContainsString('pageWindow.location.href', $content);
        $this->assertStringContainsString('getPageJQuery', $content);
        $this->assertStringContainsString('waitForJQuery', $content);
        $this->assertStringContainsString('ENDPOINT_KEYS', $content);
        $this->assertStringContainsString('endpointsComplete', $content);
        $this->assertStringContainsString('Rekam endpoint', $content);
        $this->assertStringContainsString('SS_ALL', $content);
        $this->assertStringContainsString('Array.isArray(result.items)', $content);
        $this->assertStringContainsString('result.items.length > 0', $content);
        $this->assertStringContainsString('normalizeAppUrl', $content);
        $this->assertStringContainsString('bimwinPageUrl', $content);
        $this->assertStringContainsString('bimwinUrlFromConfigCode', $content);
        $this->assertStringContainsString('recordedBimwinUrl', $content);
        $this->assertStringContainsString('appUrlFromConfigCode', $content);
        $this->assertStringContainsString('ensureAppUrl', $content);
        $this->assertStringContainsString('bw-reset', $content);
        $this->assertStringContainsString('Reset semua konfigurasi Otomasi', $content);
        $this->assertStringContainsString('BULK_MODES', $content);
        $this->assertStringContainsString('no_billing', $content);
        $this->assertStringContainsString('no_daftar', $content);
        $this->assertStringContainsString('ot-tab-bulk', $content);
        $this->assertStringContainsString('ot-bulk-start', $content);
        $this->assertStringContainsString('Bukti Bayar', $content);
        $this->assertStringContainsString('Surat Tugas', $content);
        $this->assertStringContainsString('#bw-panel #bw-reset{background:#b91c1c', $content);
        $this->assertStringContainsString('Laporan: belum terhubung', $content);
        $this->assertLessThan(strpos($content, 'Pengaturan wilayah'), strpos($content, 'id="bw-mode"'));
        $this->assertStringContainsString('right:16px', $content);
        $this->assertStringContainsString('#bw-panel button', $content);
        $this->assertStringContainsString('#bw-panel #bw-record{background:#4f46e5', $content);
        $this->assertStringContainsString('Nama provinsi', $content);
        $this->assertStringContainsString('Nama kabupaten/kota', $content);
        $this->assertStringContainsString('Nama kecamatan', $content);
        $this->assertStringNotContainsString('left:16px', $content);
        $this->assertStringNotContainsString('licenseToken', $content);
        $this->assertStringNotContainsString('ensureLicense', $content);
        $this->assertStringNotContainsString('GM_xmlhttpRequest', $content);
        $this->assertStringNotContainsString('@connect', $content);
        $this->assertStringNotContainsString('JAWA TIMUR', $content);
        $this->assertStringNotContainsString('PASURUAN', $content);
        $this->assertStringNotContainsString('PASREPAN', $content);
        $this->assertStringNotContainsString('instanceof URLSearchParams', $content);
        $this->assertStringNotContainsString('instanceof FormData', $content);
        $this->assertStringNotContainsString('e0c44a89-2a3a-461b-9430-143e761cca83', $content);
        $this->assertStringNotContainsString('e4cc7e4f-5670-4570-a6fc-0bbbeff00cee', $content);
        $this->assertStringNotContainsString('226a3ab8-ef4c-46e0-885d-5d93289eafec', $content);
        $this->assertStringNotContainsString('d3ee4e65-8f3e-4aee-af96-40594df03c8d', $content);
    }

    public function test_authenticated_user_can_save_complete_bimwin_endpoints(): void
    {
        $user = $this->user();
        $endpoints = $this->validEndpoints();

        $this->actingAs($user)
            ->patch(route('otomasi.update-endpoints'), ['endpoints' => $endpoints])
            ->assertRedirect(route('otomasi.bimwin'))
            ->assertSessionHas('status', 'Endpoint BIMWIN berhasil disimpan.');

        $this->assertSame($endpoints, $user->fresh()->bimwin_endpoints);
    }

    public function test_authenticated_user_can_reset_bimwin_endpoints(): void
    {
        $user = $this->user(['bimwin_endpoints' => $this->validEndpoints()]);

        $this->actingAs($user)
            ->delete(route('otomasi.reset-endpoints'))
            ->assertRedirect(route('otomasi.bimwin'))
            ->assertSessionHas('status', 'Endpoint BIMWIN berhasil direset.');

        $this->assertNull($user->fresh()->bimwin_endpoints);
    }

    public function test_guest_cannot_reset_bimwin_endpoints(): void
    {
        $this->delete(route('otomasi.reset-endpoints'))->assertRedirect(route('login'));
    }

    public function test_bimwin_page_shows_reset_button_only_when_endpoints_exist(): void
    {
        $user = $this->user(['bimwin_endpoints' => $this->validEndpoints()]);

        $this->actingAs($user)
            ->get(route('otomasi.bimwin'))
            ->assertOk()
            ->assertSee('Reset Endpoint')
            ->assertSee(route('otomasi.reset-endpoints'), false)
            ->assertSee('Hapus semua endpoint BIMWIN yang tersimpan');

        $user->fill(['bimwin_endpoints' => null])->save();

        $this->actingAs($user)
            ->get(route('otomasi.bimwin'))
            ->assertOk()
            ->assertDontSee('Reset Endpoint');
    }

    public function test_bimwin_endpoints_must_be_complete_or_empty(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->patch(route('otomasi.update-endpoints'), [
                'endpoints' => [
                    'cek' => $this->validEndpoints()['cek'],
                    'dialog' => '',
                    'simpan' => '',
                    'selesai' => '',
                ],
            ])
            ->assertSessionHasErrors('endpoints');

        $this->assertNull($user->fresh()->bimwin_endpoints);
    }

    public function test_bimwin_endpoints_can_be_cleared(): void
    {
        $user = $this->user(['bimwin_endpoints' => $this->validEndpoints()]);

        $this->actingAs($user)
            ->patch(route('otomasi.update-endpoints'), [
                'endpoints' => ['cek' => '', 'dialog' => '', 'simpan' => '', 'selesai' => ''],
            ])
            ->assertRedirect(route('otomasi.bimwin'));

        $this->assertNull($user->fresh()->bimwin_endpoints);
    }

    public function test_bimwin_endpoint_must_use_simkah4_https_url(): void
    {
        $user = $this->user();
        $endpoints = $this->validEndpoints();
        $endpoints['cek'] = 'https://example.com/endpoint';

        $this->actingAs($user)
            ->patch(route('otomasi.update-endpoints'), ['endpoints' => $endpoints])
            ->assertSessionHasErrors('endpoints.cek');

        $this->assertNull($user->fresh()->bimwin_endpoints);
    }

    public function test_bimwin_endpoint_rejects_query_and_fragment(): void
    {
        $user = $this->user();
        $endpoints = $this->validEndpoints();
        $endpoints['dialog'] = 'https://simkah4.kemenag.go.id/endpoint?no_daftar=1';

        $this->actingAs($user)
            ->patch(route('otomasi.update-endpoints'), ['endpoints' => $endpoints])
            ->assertSessionHasErrors('endpoints.dialog');

        $this->assertNull($user->fresh()->bimwin_endpoints);
    }

    public function test_bimwin_page_outputs_endpoints_and_app_url_in_config(): void
    {
        $user = $this->user(['bimwin_endpoints' => $this->validEndpoints()]);

        $response = $this->actingAs($user)
            ->get(route('otomasi.bimwin'))
            ->assertOk()
            ->assertSee('4/4 tersimpan')
            ->assertSee($this->validEndpoints()['cek'], false)
            ->assertSee('appUrl')
            ->assertSee('bimwinUrl')
            ->assertDontSee('licenseUrl')
            ->assertDontSee('licenseToken');

        $appUrl = rtrim(url('/'), '/');
        $content = $response->getContent();

        $this->assertStringContainsString($appUrl, $content);
        $this->assertStringContainsString($appUrl.'/otomasi/bimwin', $content);
        $this->assertStringNotContainsString($appUrl.'/api/otomasi/license', $content);
        $this->assertStringNotContainsString($appUrl.'/otomasi/bimwin/bimwin', $content);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function user(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    /**
     * @return array{cek: string, dialog: string, simpan: string, selesai: string}
     */
    private function validEndpoints(): array
    {
        return [
            'cek' => 'https://simkah4.kemenag.go.id/endpoint-cek',
            'dialog' => 'https://simkah4.kemenag.go.id/endpoint-dialog',
            'simpan' => 'https://simkah4.kemenag.go.id/endpoint-simpan',
            'selesai' => 'https://simkah4.kemenag.go.id/endpoint-selesai',
        ];
    }
}
