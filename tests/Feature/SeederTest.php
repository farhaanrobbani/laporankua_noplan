<?php

namespace Tests\Feature;

use App\Models\ReportTemplate;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_membuat_admin_kua_di_non_produksi(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@example.com')->first();

        $this->assertNotNull($admin);
        $this->assertSame('admin', $admin->role);
        $this->assertSame('active', $admin->status);
        $this->assertNotNull($admin->email_verified_at);
    }

    public function test_seeder_membuat_semua_template_dengan_owner_admin(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $this->assertSame(11, ReportTemplate::count());
        $this->assertSame(11, ReportTemplate::where('user_id', $admin->id)->count());
    }

    public function test_template_seeder_butuh_admin_terlebih_dahulu(): void
    {
        $this->expectException(RuntimeException::class);

        $this->seed(TemplateSeeder::class);
    }

    public function test_command_kua_setup_membuat_admin_dan_template(): void
    {
        $this->artisan('kua:setup', [
            '--name' => 'Admin KUA Uji',
            '--email' => 'kua@example.test',
            '--password' => 'rahasia-uji',
            '--kua-name' => 'KUA Uji',
            '--provinsi' => 'Jawa Timur',
            '--kabkota' => 'Kabupaten Uji',
            '--kecamatan' => 'Kecamatan Uji',
        ])->assertSuccessful();

        $admin = User::where('email', 'kua@example.test')->firstOrFail();

        $this->assertSame('Admin KUA Uji', $admin->name);
        $this->assertSame('admin', $admin->role);
        $this->assertSame('active', $admin->status);
        $this->assertSame('KUA Uji', $admin->nama_kantor);
        $this->assertSame('Jawa Timur', $admin->provinsi);
        $this->assertSame('Kabupaten Uji', $admin->kota_kabupaten);
        $this->assertSame('Kecamatan Uji', $admin->kecamatan);
        $this->assertSame(11, ReportTemplate::count());
    }

    public function test_command_kua_setup_memperbarui_akun_yang_sama(): void
    {
        $this->artisan('kua:setup', [
            '--email' => 'kua@example.test',
            '--password' => 'password-lama',
        ])->assertSuccessful();

        $this->artisan('kua:setup', [
            '--email' => 'kua@example.test',
            '--password' => 'password-baru',
            '--name' => 'Nama Baru',
        ])->assertSuccessful();

        $this->assertSame(1, User::count());
        $this->assertSame('Nama Baru', User::first()->name);
    }
}
