<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Kolom dedup per table_name. Diisi untuk import lama yang null
     * sehingga mode append (skip baris sama) dan mode normal
     * (overwrite baris ber-key sama) aktif untuk semua tabel.
     */
    private const DEDUP_COLUMN_BY_TABLE = [
        'laporan pendaftaran nikah' => 'No. Daftar',
        'laporan peristiwa nikah' => 'No. Daftar',
        'laporan model l3' => 'Nomor Perforasi',
        'laporan simponi' => 'No Pendaftaran',
        'laporan akta nikah' => 'No. Daftar',
        'laporan model l5' => 'NIK Calon Suami',
    ];

    /**
     * Sumber nilai di row_data untuk mengisi dedup_key_value per table_name.
     */
    private const ROW_KEY_BY_TABLE = [
        'laporan pendaftaran nikah' => 'Nomor Daftar',
        'laporan peristiwa nikah' => 'Nomor Daftar',
        'laporan model l3' => 'Nomor Perforasi',
        'laporan simponi' => 'No Pendaftaran',
        'laporan akta nikah' => 'No. Daftar',
        'laporan model l5' => 'NIK Calon Suami',
    ];

    public function up(): void
    {
        foreach (self::DEDUP_COLUMN_BY_TABLE as $table => $column) {
            DB::table('imports')
                ->whereNull('dedup_column')
                ->where('table_name', $table)
                ->update(['dedup_column' => $column]);
        }

        foreach (self::ROW_KEY_BY_TABLE as $table => $rowKey) {
            $importIds = DB::table('imports')->where('table_name', $table)->pluck('id');
            if ($importIds->isEmpty()) {
                continue;
            }

            DB::table('import_data')
                ->whereIn('import_id', $importIds)
                ->whereNull('dedup_key_value')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($rowKey) {
                    foreach ($rows as $row) {
                        $data = json_decode((string) $row->row_data, true) ?? [];
                        $value = trim((string) ($data[$rowKey] ?? ''));
                        if ($value !== '') {
                            DB::table('import_data')->where('id', $row->id)->update(['dedup_key_value' => $value]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        // Backfill data; tidak perlu dirollback agar aman dijalankan ulang.
    }
};
