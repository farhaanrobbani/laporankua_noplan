<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ImportDataExport implements FromCollection, WithHeadings
{
    /**
     * @param  string[]  $headings
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __construct(
        protected array $headings,
        protected array $rows,
    ) {}

    public function headings(): array
    {
        return $this->headings;
    }

    public function collection(): Collection
    {
        return collect($this->rows)->map(
            fn (array $row) => collect($this->headings)->mapWithKeys(
                fn (string $heading) => [$heading => $row[$heading] ?? null]
            )->values()
        );
    }
}
