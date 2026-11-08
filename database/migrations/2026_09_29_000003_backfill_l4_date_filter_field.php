<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD_FIELD = 'Tanggal dan Jam Setor';

    private const NEW_FIELD = 'Tanggal Akad';

    /**
     * Pindahkan filter tanggal laporan L4 (grouped_detail) dari
     * Tanggal Setor ke Tanggal Akad pada template dan report yang sudah ada.
     * Seeder memakai firstOrCreate sehingga row lama tidak ikut ter-update.
     */
    public function up(): void
    {
        $template = DB::table('report_templates')
            ->where('name', 'Laporan L4 Simponi')
            ->first();

        if ($template !== null) {
            $layout = json_decode((string) $template->layout_json, true) ?? [];
            $current = $layout['table_layout']['aggregation']['date_filter_field'] ?? null;
            if ($current === self::OLD_FIELD) {
                $layout['table_layout']['aggregation']['date_filter_field'] = self::NEW_FIELD;
                DB::table('report_templates')
                    ->where('id', $template->id)
                    ->update(['layout_json' => json_encode($layout)]);
            }
        }

        DB::table('reports')
            ->where('config_json', 'like', '%"grouped_detail"%')
            ->orderBy('id')
            ->chunkById(200, function ($reports) {
                foreach ($reports as $report) {
                    $config = json_decode((string) $report->config_json, true) ?? [];
                    $current = $config['table_layout']['aggregation']['date_filter_field'] ?? null;
                    if (($config['table_layout']['type'] ?? null) !== 'grouped_detail' || $current !== self::OLD_FIELD) {
                        continue;
                    }
                    $config['table_layout']['aggregation']['date_filter_field'] = self::NEW_FIELD;
                    DB::table('reports')
                        ->where('id', $report->id)
                        ->update(['config_json' => json_encode($config)]);
                }
            });
    }

    public function down(): void
    {
        // Backfill data; tidak perlu dirollback agar aman dijalankan ulang.
    }
};
