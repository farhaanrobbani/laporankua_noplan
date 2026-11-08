<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'file_name',
        'table_name',
        'is_append',
        'default_sort_column',
        'default_sort_direction',
        'file_path',
        'file_size',
        'sheet_name',
        'dedup_column',
        'total_rows',
        'imported_rows',
        'failed_rows',
        'status',
        'imported_at',
        'error_log',
    ];

    protected function casts(): array
    {
        return [
            'imported_at' => 'datetime',
            'error_log' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function importData(): HasMany
    {
        return $this->hasMany(ImportData::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * Parse filename menjadi table_name.
     * Contoh: "laporan-peristiwa-nikah-08-2026.xlsx" → "laporan peristiwa nikah"
     */
    public static function parseTableName(string $filename): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $name = preg_replace('/(-\d+)+$/', '', $name);
        $name = str_replace('-', ' ', $name);
        $name = preg_replace('/\s+/', ' ', trim($name));

        return $name;
    }

    /**
     * Parse tanggal dari nama file (format: *-MM-YYYY.*).
     * Contoh: "laporan-model-l3-08-2026.xlsx" → ['month' => 8, 'year' => 2026, 'date' => '2026-08-01']
     *
     * @return array{month: int, year: int, date: string}|null
     */
    public static function parseFileDate(string $filename): ?array
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);

        if (preg_match('/-(\d{1,2})-(\d{4})$/', $name, $m)) {
            $month = (int) $m[1];
            $year = (int) $m[2];

            if ($month >= 1 && $month <= 12) {
                return [
                    'month' => $month,
                    'year' => $year,
                    'date' => sprintf('%04d-%02d-01', $year, $month),
                ];
            }
        }

        return null;
    }

    /**
     * Daftar kolom gabungan dari baris data (maks 1000 baris pertama).
     *
     * @return string[]
     */
    public function availableColumns(): array
    {
        $columns = [];

        $rows = $this->importData()->orderBy('row_number')->take(1000)->pluck('row_data');

        foreach ($rows as $rowData) {
            $decoded = is_string($rowData) ? json_decode($rowData, true) : $rowData;

            if (! is_array($decoded)) {
                continue;
            }

            foreach (array_keys($decoded) as $key) {
                if (! in_array($key, $columns, true)) {
                    $columns[] = $key;
                }
            }
        }

        return $columns;
    }
}
