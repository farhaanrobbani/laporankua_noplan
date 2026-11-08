<?php

namespace App\Services;

use App\Exports\ImportDataExport;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as MaatwebsiteExcel;
use Maatwebsite\Excel\Facades\Excel;

class ExcelExportService
{
    /**
     * @param  array{title: string, headings: string[], rows: array<int, array<string, mixed>>, total: int, generated_at: string}  $dataset
     */
    public function generate(array $dataset, string $relativePath): void
    {
        $raw = Excel::raw(
            new ImportDataExport($dataset['headings'], $dataset['rows']),
            MaatwebsiteExcel::XLSX
        );

        Storage::disk('local')->put($relativePath, $raw);
    }
}
