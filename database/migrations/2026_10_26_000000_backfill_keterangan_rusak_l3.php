<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Baris laporan model L3 dengan Status "RUSAK" (buku rusak, Tanggal Akad
     * kosong) sebelumnya salah diklasifikasikan sebagai "Duplikat" oleh
     * postProcessModelL3. Klasifikasi baru menandainya "Rusak"; migrasi ini
     * menyelaraskan data L3 yang sudah terlanjur diimpor.
     */
    public function up(): void
    {
        $importIds = DB::table('imports')
            ->where('table_name', 'laporan model l3')
            ->pluck('id');

        foreach ($importIds as $importId) {
            DB::table('import_data')
                ->where('import_id', $importId)
                ->select('id', 'row_data')
                ->chunkById(500, function ($rows): void {
                    foreach ($rows as $row) {
                        $data = is_string($row->row_data)
                            ? json_decode($row->row_data, true)
                            : $row->row_data;

                        if (! is_array($data)) {
                            continue;
                        }

                        if (mb_strtoupper(trim((string) ($data['Status'] ?? ''))) !== 'RUSAK') {
                            continue;
                        }

                        if (($data['Keterangan'] ?? '') === 'Rusak') {
                            continue;
                        }

                        $data['Keterangan'] = 'Rusak';

                        DB::table('import_data')
                            ->where('id', $row->id)
                            ->update([
                                'row_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                'updated_at' => now(),
                            ]);
                    }
                });
        }
    }

    public function down(): void
    {
        // Tidak dikembalikan: klasifikasi "Rusak" adalah hasil koreksi data.
    }
};
