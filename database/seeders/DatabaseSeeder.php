<?php

namespace Database\Seeders;

use App\Support\KuaSetup;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = KuaSetup::ensureAdmin();

        $this->command?->info('Akun admin KUA: '.$admin->email);

        $this->call(TemplateSeeder::class);
    }
}
