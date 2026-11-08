<?php

namespace Tests\Feature;

use App\Services\RusakDetectionService;
use Tests\TestCase;

class RusakDetectionTest extends TestCase
{
    public function test_reprint_lintas_bulan_tidak_menimbulkan_celah_dan_nomor_lain_dilewati(): void
    {
        $service = app(RusakDetectionService::class);
        $rows = [
            ['Nomor Perforasi' => '116741251', 'Tanggal Cetak' => '01-12-2025'],
            ['Nomor Perforasi' => '116741252', 'Tanggal Cetak' => '02-12-2025'],
            ['Nomor Perforasi' => '116741253', 'Tanggal Cetak' => '03-12-2025'],
            ['Nomor Perforasi' => '116741254', 'Tanggal Cetak' => '04-12-2025'],
            // 255 tidak ada di Desember, tetapi muncul sebagai cetak ulang di April.
            ['Nomor Perforasi' => '116741256', 'Tanggal Cetak' => '05-12-2025'],
            ['Nomor Perforasi' => '116741257', 'Tanggal Cetak' => '06-12-2025'],
            ['Nomor Perforasi' => '116741258', 'Tanggal Cetak' => '05-01-2026'],
            ['Nomor Perforasi' => '116741259', 'Tanggal Cetak' => '06-01-2026'],
            ['Nomor Perforasi' => '116741255', 'Tanggal Cetak' => '05-04-2026'],
            ['Nomor Perforasi' => '116741260', 'Tanggal Cetak' => '10-04-2026'],
            ['Nomor Perforasi' => '116741261', 'Tanggal Cetak' => '10-04-2026'],
            ['Nomor Perforasi' => '116741263', 'Tanggal Cetak' => '10-04-2026'],
        ];

        $gaps = $service->detectMissingByMonth($rows);

        $this->assertSame([
            ['prefix' => '116741', 'number' => 116741262, 'month' => '2026-04', 'date' => '2026-04-10'],
        ], $gaps);

        $counts = $service->countsByMonth($rows, 2026, [1, 2, 3, 4]);
        $this->assertSame(0, $counts['bulan'][1]);
        $this->assertSame(1, $counts['bulan'][4]);
        $this->assertSame(1, $counts['total']);

        $desember = $service->countsByMonth($rows, 2025, [12]);
        $this->assertSame(0, $desember['bulan'][12], '255 dianggap ada karena cetak ulang April');
        $this->assertSame(0, $desember['total']);
    }

    public function test_baris_tanpa_tanggal_tetap_mendeteksi_celah_interior(): void
    {
        $gaps = app(RusakDetectionService::class)->detectMissingByMonth([
            ['Nomor Perforasi' => '116741001'],
            ['Nomor Perforasi' => '116741003'],
        ]);

        $this->assertSame([
            ['prefix' => '116741', 'number' => 116741002, 'month' => '', 'date' => null],
        ], $gaps);
    }
}
