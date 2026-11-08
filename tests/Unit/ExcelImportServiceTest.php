<?php

namespace Tests\Unit;

use App\Services\ExcelImportService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ExcelImportServiceTest extends TestCase
{
    private function makeXlsx(array $sheets): string
    {
        $spreadsheet = new Spreadsheet;
        $first = true;

        foreach ($sheets as $name => $rows) {
            $sheet = $first ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $sheet->setTitle($name);

            foreach ($rows as $r => $row) {
                foreach (array_values($row) as $c => $value) {
                    $sheet->setCellValue([$c + 1, $r + 1], $value);
                }
            }

            $first = false;
        }

        $path = sys_get_temp_dir().'/unit_'.uniqid().'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    public function test_get_sheet_names(): void
    {
        $path = $this->makeXlsx(['Alpha' => [['A']], 'Beta' => [['B']]]);

        $names = app(ExcelImportService::class)->getSheetNames($path);

        $this->assertSame(['Alpha', 'Beta'], $names);
        unlink($path);
    }

    public function test_preview_normalisasi_header(): void
    {
        $path = $this->makeXlsx(['Sheet1' => [
            [' Nama ', '', 'Nama', 'Nilai'],
            ['Budi', 'x', 'Budi2', 100],
            [null, null, null, null],
            ['Siti', 'y', 'Siti2', 200],
        ]]);

        $preview = app(ExcelImportService::class)->previewRows($path, 'Sheet1', 100);

        // Trim, kolom kosong jadi "Kolom B", duplikat diberi suffix
        $this->assertSame(['Nama', 'Kolom B', 'Nama (2)', 'Nilai'], $preview['headers']);
        // Baris kosong tengah dihitung dalam total
        $this->assertSame(3, $preview['total']);
        $this->assertCount(3, $preview['rows']);
        $this->assertSame('Sheet1', $preview['sheet']);
        unlink($path);
    }

    public function test_preview_dibatasi_limit(): void
    {
        $rows = [['H']];
        for ($i = 0; $i < 150; $i++) {
            $rows[] = ['R'.$i];
        }
        $path = $this->makeXlsx(['Sheet1' => $rows]);

        $preview = app(ExcelImportService::class)->previewRows($path, 'Sheet1', 100);

        $this->assertSame(150, $preview['total']);
        $this->assertCount(100, $preview['rows']);
        unlink($path);
    }

    public function test_preview_sheet_tidak_ada_pakai_aktif(): void
    {
        $path = $this->makeXlsx(['Aktif' => [['H'], ['R']]]);

        $preview = app(ExcelImportService::class)->previewRows($path, 'TidakAda');

        $this->assertSame('Aktif', $preview['sheet']);
        $this->assertSame(1, $preview['total']);
        unlink($path);
    }
}
