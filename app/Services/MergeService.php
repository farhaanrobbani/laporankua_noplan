<?php

namespace App\Services;

use App\Models\Import;
use App\Models\ImportData;

class MergeService
{
    /**
     * Auto-JOIN data dari multiple imports berdasarkan kolom yang sama (intersection).
     * Hanya baris yang cocok di SEMUA file yang ditampilkan (INNER JOIN).
     *
     * @param  int[]  $importIds
     * @return array{headings: string[], rows: array<int, array<string, mixed>>, total: int, shared_columns: string[]}
     */
    public function autoMergeBySharedColumns(array $importIds): array
    {
        if (count($importIds) < 2) {
            return ['headings' => [], 'rows' => [], 'total' => 0, 'shared_columns' => []];
        }

        // 1. Dapatkan kolom per file
        $importColumns = [];
        foreach ($importIds as $importId) {
            $import = Import::where('id', $importId)->first();
            if ($import) {
                $importColumns[$importId] = $import->availableColumns();
            }
        }

        if (count($importColumns) < 2) {
            return ['headings' => [], 'rows' => [], 'total' => 0, 'shared_columns' => []];
        }

        // 2. Cari intersection, exclude "No" (row number)
        $sharedColumns = null;
        foreach ($importColumns as $cols) {
            $filteredCols = array_values(array_filter($cols, fn ($c) => $c !== 'No'));
            if ($sharedColumns === null) {
                $sharedColumns = $filteredCols;
            } else {
                $sharedColumns = array_values(array_intersect($sharedColumns, $filteredCols));
            }
        }

        if (empty($sharedColumns)) {
            return ['headings' => [], 'rows' => [], 'total' => 0, 'shared_columns' => []];
        }

        // 3. Pilih kolom join key terbaik
        $joinKeyColumns = $this->selectBestJoinKey($sharedColumns);

        // 4. Dapatkan semua kolom (union)
        $allColumns = $this->getAllColumns($importIds);

        // 5. Index semua baris per file berdasarkan composite key
        $indexedData = [];
        $keyFileCount = [];

        foreach ($importIds as $importId) {
            $import = Import::where('id', $importId)->first();
            if (! $import) {
                continue;
            }

            $rows = ImportData::where('import_id', $importId)
                ->orderBy('row_number')
                ->cursor()
                ->map(fn (ImportData $record) => $record->row_data ?? [])
                ->all();

            $seenKeys = [];
            foreach ($rows as $row) {
                $key = $this->buildCompositeKey($row, $joinKeyColumns);
                if ($key === '') {
                    continue;
                }

                $seenKeys[$key] = true;

                if (! isset($indexedData[$key])) {
                    $indexedData[$key] = [];
                    $keyFileCount[$key] = 0;
                }

                foreach ($row as $col => $val) {
                    $indexedData[$key][$col] = $val;
                }
            }

            foreach (array_keys($seenKeys) as $key) {
                $keyFileCount[$key] = ($keyFileCount[$key] ?? 0) + 1;
            }
        }

        // 6. Bangun merged rows — hanya baris yang ada di minimal 2 file (INNER JOIN)
        $mergedRows = [];

        foreach ($indexedData as $key => $row) {
            if (($keyFileCount[$key] ?? 0) < 2) {
                continue;
            }

            $mergedRow = [];
            foreach ($allColumns as $col) {
                $mergedRow[$col] = $row[$col] ?? null;
            }
            $mergedRows[] = $mergedRow;
        }

        return [
            'headings' => $allColumns,
            'rows' => $mergedRows,
            'total' => count($mergedRows),
            'shared_columns' => $joinKeyColumns,
        ];
    }

    /**
     * Bangun composite key dari baris berdasarkan kolom-kolom tertentu.
     */
    private function buildCompositeKey(array $row, array $columns): string
    {
        $parts = [];
        foreach ($columns as $col) {
            $val = $row[$col] ?? null;
            $parts[] = $val !== null ? (string) $val : '';
        }

        return implode('|', $parts);
    }

    /**
     * Pilih kolom join key terbaik dari shared columns.
     * Prioritas: kolom identifier (Nomor, NIK, Daftar, ID) > semua.
     */
    private function selectBestJoinKey(array $sharedColumns): array
    {
        $nomorDaftar = array_values(array_filter($sharedColumns, fn ($c) => mb_strtolower($c) === 'nomor daftar'));
        if ($nomorDaftar !== []) {
            return $nomorDaftar;
        }

        $nameDate = array_values(array_filter($sharedColumns, function ($c) {
            return in_array($c, ['Nama Suami', 'Nama Istri', 'Tanggal Nikah'], true);
        }));
        if (count($nameDate) === 3) {
            return $nameDate;
        }

        return $sharedColumns;
    }

    /**
     * Build dataset untuk laporan dari auto-merge results.
     *
     * @param  int[]  $importIds
     * @param  string[]  $fields
     * @return array{title: string, headings: string[], rows: array<int, array<string, mixed>>, total: int, generated_at: string}
     */
    public function buildMergedDataset(
        array $importIds,
        array $fields,
        ?string $search = null,
        ?string $filterColumn = null,
        ?string $filterValue = null,
        ?string $sortColumn = null,
        string $sortDirection = 'asc',
        ?int $limit = null,
    ): array {
        $mergeResult = $this->autoMergeBySharedColumns($importIds);
        $rows = $mergeResult['rows'];

        if ($search !== null && $search !== '') {
            $lowerSearch = mb_strtolower($search);
            $rows = array_filter($rows, function ($row) use ($lowerSearch) {
                foreach ($row as $val) {
                    if ($val !== null && mb_strpos(mb_strtolower((string) $val), $lowerSearch) !== false) {
                        return true;
                    }
                }

                return false;
            });
        }

        if ($filterColumn !== null && $filterColumn !== '' && $filterValue !== null && $filterValue !== '') {
            $lowerFilter = mb_strtolower($filterValue);
            $rows = array_filter($rows, function ($row) use ($filterColumn, $lowerFilter) {
                $val = $row[$filterColumn] ?? null;

                return $val !== null && mb_strpos(mb_strtolower((string) $val), $lowerFilter) !== false;
            });
        }

        $rows = array_values($rows);

        if ($sortColumn !== null && $sortColumn !== '' && isset($rows[0][$sortColumn])) {
            $dir = strtolower($sortDirection) === 'desc' ? 'desc' : 'asc';
            usort($rows, function ($a, $b) use ($sortColumn, $dir) {
                $valA = $a[$sortColumn] ?? '';
                $valB = $b[$sortColumn] ?? '';

                $cmp = strcasecmp((string) $valA, (string) $valB);

                return $dir === 'desc' ? -$cmp : $cmp;
            });
        }

        if ($limit !== null) {
            $rows = array_slice($rows, 0, $limit);
        }

        $filteredRows = [];
        foreach ($rows as $row) {
            $filteredRow = [];
            foreach ($fields as $field) {
                $filteredRow[$field] = $row[$field] ?? null;
            }
            $filteredRows[] = $filteredRow;
        }

        return [
            'title' => 'Gabungan '.count($importIds).' file import',
            'headings' => array_values($fields),
            'rows' => $filteredRows,
            'total' => count($filteredRows),
            'generated_at' => now()->format('d M Y H:i'),
        ];
    }

    /**
     * Build dataset untuk laporan dari auto-merge results (untuk print view).
     *
     * @param  int[]  $importIds
     * @param  string[]  $fields
     * @return array{title: string, headings: string[], rows: array<int, array<string, mixed>>, total: int, generated_at: string}
     */
    public function buildConcatDataset(
        array $importIds,
        array $fields,
        ?string $search = null,
        ?string $filterColumn = null,
        ?string $filterValue = null,
        ?string $sortColumn = null,
        string $sortDirection = 'asc',
        ?int $limit = null,
    ): array {
        $concatResult = $this->concatImports($importIds);
        $rows = $concatResult['rows'];

        if ($search !== null && $search !== '') {
            $lowerSearch = mb_strtolower($search);
            $rows = array_filter($rows, function ($row) use ($lowerSearch) {
                foreach ($row as $key => $val) {
                    if (is_string($key) && str_starts_with($key, '_')) {
                        continue;
                    }
                    if ($val !== null && mb_strpos(mb_strtolower((string) $val), $lowerSearch) !== false) {
                        return true;
                    }
                }

                return false;
            });
        }

        if ($filterColumn !== null && $filterColumn !== '' && $filterValue !== null && $filterValue !== '') {
            $lowerFilter = mb_strtolower($filterValue);
            $rows = array_filter($rows, function ($row) use ($filterColumn, $lowerFilter) {
                $val = $row[$filterColumn] ?? null;

                return $val !== null && mb_strpos(mb_strtolower((string) $val), $lowerFilter) !== false;
            });
        }

        $rows = array_values($rows);

        if ($sortColumn !== null && $sortColumn !== '' && isset($rows[0][$sortColumn])) {
            $dir = strtolower($sortDirection) === 'desc' ? 'desc' : 'asc';
            usort($rows, function ($a, $b) use ($sortColumn, $dir) {
                $valA = $a[$sortColumn] ?? '';
                $valB = $b[$sortColumn] ?? '';

                $cmp = strcasecmp((string) $valA, (string) $valB);

                return $dir === 'desc' ? -$cmp : $cmp;
            });
        }

        if ($limit !== null) {
            $rows = array_slice($rows, 0, $limit);
        }

        $filteredRows = [];
        foreach ($rows as $row) {
            $filteredRow = [];
            foreach ($fields as $field) {
                $filteredRow[$field] = $row[$field] ?? null;
            }
            $filteredRows[] = $filteredRow;
        }

        return [
            'title' => 'Gabungan '.count($importIds).' file import',
            'headings' => array_values($fields),
            'rows' => $filteredRows,
            'total' => count($filteredRows),
            'generated_at' => now()->format('d M Y H:i'),
        ];
    }

    /**
     * Get columns shared between multiple imports.
     *
     * @param  int[]  $importIds
     * @return string[]
     */
    public function getSharedColumns(array $importIds): array
    {
        if (count($importIds) < 2) {
            return [];
        }

        $allColumns = [];
        $columnCounts = [];

        foreach ($importIds as $importId) {
            $import = Import::where('id', $importId)->first();

            if (! $import) {
                continue;
            }

            $columns = $import->availableColumns();

            foreach ($columns as $col) {
                if (! in_array($col, $allColumns, true)) {
                    $allColumns[] = $col;
                    $columnCounts[$col] = 0;
                }
                $columnCounts[$col] = ($columnCounts[$col] ?? 0) + 1;
            }
        }

        $totalImports = count($importIds);

        return array_values(array_filter($allColumns, function ($col) use ($columnCounts) {
            return ($columnCounts[$col] ?? 0) >= 2;
        }));
    }

    /**
     * Get all unique columns across multiple imports.
     *
     * @param  int[]  $importIds
     * @return string[]
     */
    public function getAllColumns(array $importIds): array
    {
        $allColumns = [];

        foreach ($importIds as $importId) {
            $import = Import::where('id', $importId)->first();

            if (! $import) {
                continue;
            }

            foreach ($import->availableColumns() as $col) {
                if (! in_array($col, $allColumns, true)) {
                    $allColumns[] = $col;
                }
            }
        }

        return $allColumns;
    }

    /**
     * Concat data dari multiple imports (append, tanpa JOIN).
     * Digunakan oleh data table view.
     *
     * @param  int[]  $importIds
     * @return array{headings: string[], rows: array<int, array<string, mixed>>, total: int}
     */
    public function concatImports(array $importIds): array
    {
        $allColumns = $this->getAllColumns($importIds);
        $allRows = [];

        foreach ($importIds as $importId) {
            $import = Import::where('id', $importId)->first();

            if (! $import) {
                continue;
            }

            $rows = ImportData::where('import_id', $importId)
                ->orderBy('row_number')
                ->cursor()
                ->map(fn (ImportData $record) => [
                    '_row_id' => $record->id,
                    '_import_id' => $record->import_id,
                ] + ($record->row_data ?? []))
                ->all();

            foreach ($rows as $row) {
                $normalizedRow = [];
                foreach ($allColumns as $col) {
                    $normalizedRow[$col] = $row[$col] ?? null;
                }
                $normalizedRow['_row_id'] = $row['_row_id'];
                $normalizedRow['_import_id'] = $row['_import_id'];
                $allRows[] = $normalizedRow;
            }
        }

        return [
            'headings' => $allColumns,
            'rows' => $allRows,
            'total' => count($allRows),
        ];
    }

    /**
     * JOIN data dari multiple imports berdasarkan kolom tertentu.
     * Digunakan oleh data table view.
     *
     * @param  int[]  $importIds
     * @return array{headings: string[], rows: array<int, array<string, mixed>>, total: int}
     */
    public function mergeByColumn(array $importIds, string $joinColumn): array
    {
        $allColumns = [];
        $indexedData = [];
        $allKeys = [];

        foreach ($importIds as $importId) {
            $import = Import::where('id', $importId)->first();

            if (! $import) {
                continue;
            }

            $columns = $import->availableColumns();
            foreach ($columns as $col) {
                if (! in_array($col, $allColumns, true)) {
                    $allColumns[] = $col;
                }
            }

            $rows = ImportData::where('import_id', $importId)
                ->orderBy('row_number')
                ->cursor()
                ->map(fn (ImportData $record) => [
                    '_row_id' => $record->id,
                    '_import_id' => $record->import_id,
                ] + ($record->row_data ?? []))
                ->all();

            foreach ($rows as $row) {
                $key = isset($row[$joinColumn]) ? (string) $row[$joinColumn] : null;

                if ($key === null || $key === '') {
                    $key = '__unmatched_'.$importId.'_'.(array_key_exists('__unmatched_'.$importId, $allKeys) ? count($allKeys['__unmatched_'.$importId]) : 0);
                    $allKeys[$key] = true;
                } else {
                    $allKeys[$key] = true;
                }

                if (! isset($indexedData[$key])) {
                    $indexedData[$key] = [];
                }

                foreach ($row as $col => $val) {
                    $indexedData[$key][$col] = $val;
                }
            }
        }

        $mergedRows = [];
        foreach ($indexedData as $key => $row) {
            $mergedRow = [];
            foreach ($allColumns as $col) {
                $mergedRow[$col] = $row[$col] ?? null;
            }
            $mergedRow['_row_id'] = $row['_row_id'] ?? null;
            $mergedRow['_import_id'] = $row['_import_id'] ?? null;
            $mergedRows[] = $mergedRow;
        }

        usort($mergedRows, function ($a, $b) use ($joinColumn) {
            $valA = $a[$joinColumn] ?? '';
            $valB = $b[$joinColumn] ?? '';

            return strcasecmp((string) $valA, (string) $valB);
        });

        return [
            'headings' => $allColumns,
            'rows' => $mergedRows,
            'total' => count($mergedRows),
        ];
    }
}
