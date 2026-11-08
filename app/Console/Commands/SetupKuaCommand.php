<?php

namespace App\Console\Commands;

use App\Support\KuaSetup;
use Database\Seeders\TemplateSeeder;
use Illuminate\Console\Command;
use Throwable;

class SetupKuaCommand extends Command
{
    protected $signature = 'kua:setup
        {--name= : Nama admin KUA}
        {--email= : Email admin KUA}
        {--password= : Password admin KUA}
        {--kua-name= : Nama kantor KUA}
        {--provinsi= : Provinsi}
        {--kabkota= : Kabupaten/kota}
        {--kecamatan= : Kecamatan}';

    protected $description = 'Membuat atau memperbarui satu akun admin KUA dan menyiapkan template laporan.';

    public function handle(): int
    {
        try {
            $user = KuaSetup::ensureAdmin([
                'name' => $this->option('name'),
                'email' => $this->option('email'),
                'password' => $this->option('password'),
                'kua_name' => $this->option('kua-name'),
                'provinsi' => $this->option('provinsi'),
                'kabkota' => $this->option('kabkota'),
                'kecamatan' => $this->option('kecamatan'),
            ]);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->call('db:seed', [
            '--class' => TemplateSeeder::class,
            '--force' => true,
        ]);

        $this->info('Akun admin KUA siap: '.$user->email);

        return self::SUCCESS;
    }
}
