<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile/info');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile/info');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile/info');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_profile_region_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update-desa'), [
                'provinsi' => 'Provinsi Uji',
                'kecamatan' => 'Kecamatan Uji',
                'kota_kabupaten' => 'Kabupaten Uji',
                'daftar_desa' => "Desa A\nDesa B",
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.daftar-desa'));

        $user->refresh();

        $this->assertSame('Provinsi Uji', $user->provinsi);
        $this->assertSame('Kecamatan Uji', $user->kecamatan);
        $this->assertSame('Kabupaten Uji', $user->kota_kabupaten);
        $this->assertSame(['Desa A', 'Desa B'], $user->daftar_desa);
    }

    public function test_profile_region_fields_use_neutral_placeholders(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.daftar-desa'))
            ->assertOk()
            ->assertSee('Nama provinsi')
            ->assertSee('Nama kabupaten/kota')
            ->assertSee('Nama kecamatan')
            ->assertDontSee('Contoh: Jawa Timur')
            ->assertDontSee('Contoh: Kab. Malang');
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile/delete')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile/delete');

        $this->assertNotNull($user->fresh());
    }
}
