<?php

namespace App\Console\Commands;

use App\Models\Import;
use App\Services\L3ReportService;
use Illuminate\Console\Command;

class ReprocessL3Command extends Command
{
    protected $signature = 'l3:reprocess
        {--user= : ID user yang akan diproses}
        {--all : Proses semua user yang punya import L3}
        {--dry-run : Hitung perubahan tanpa menyimpan}';

    protected $description = 'Hitung ulang status duplikat dan Tanggal Cetak laporan model L3';

    public function handle(L3ReportService $service): int
    {
        $userId = $this->option('user');
        $all = (bool) $this->option('all');

        if ($userId === null && ! $all) {
            $this->error('Gunakan --user=ID atau --all.');

            return self::FAILURE;
        }

        if ($userId !== null && $all) {
            $this->error('Gunakan salah satu: --user=ID atau --all.');

            return self::FAILURE;
        }

        $userIds = $userId !== null
            ? [(int) $userId]
            : Import::query()
                ->where('table_name', 'laporan model l3')
                ->distinct()
                ->orderBy('user_id')
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();

        if ($userIds === []) {
            $this->info('Tidak ada import L3 yang perlu diproses.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $totals = ['imports' => 0, 'processed' => 0, 'updated' => 0, 'duplicates' => 0, 'skipped' => 0];

        foreach ($userIds as $id) {
            $stats = $service->reprocessUser($id, $dryRun);

            foreach ($totals as $key => $value) {
                $totals[$key] = $value + $stats[$key];
            }

            $this->line(sprintf(
                'User %d: %d import, %d baris, %d diubah, %d duplikat, %d dilewati',
                $id,
                $stats['imports'],
                $stats['processed'],
                $stats['updated'],
                $stats['duplicates'],
                $stats['skipped'],
            ));
        }

        $this->info(sprintf(
            '%s: %d import, %d baris, %d diubah, %d duplikat, %d dilewati',
            $dryRun ? 'Dry run' : 'Selesai',
            $totals['imports'],
            $totals['processed'],
            $totals['updated'],
            $totals['duplicates'],
            $totals['skipped'],
        ));

        return self::SUCCESS;
    }
}
