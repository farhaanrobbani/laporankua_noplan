<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportData extends Model
{
    use HasFactory;

    protected $table = 'import_data';

    protected $fillable = [
        'import_id',
        'row_data',
        'row_number',
        'dedup_key_value',
    ];

    protected function casts(): array
    {
        return [
            'row_data' => 'array',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    public function scopeForImport(Builder $query, int $importId): Builder
    {
        return $query->where('import_id', $importId);
    }

    public function scopeSearch(Builder $query, ?string $keyword): Builder
    {
        $keyword = trim((string) $keyword);

        if ($keyword === '') {
            return $query;
        }

        return $query->whereRaw(
            'LOWER(row_data) LIKE ?',
            ['%'.mb_strtolower(addcslashes($keyword, '%_\\')).'%']
        );
    }

    public function scopeFilterColumn(Builder $query, ?string $column, ?string $value): Builder
    {
        $column = trim((string) $column);
        $value = trim((string) $value);

        if ($column === '' || $value === '') {
            return $query;
        }

        $extract = $query->getModel()->getConnection()->getDriverName() === 'mysql'
            ? 'JSON_UNQUOTE(JSON_EXTRACT(row_data, ?))'
            : 'json_extract(row_data, ?)';

        return $query->whereRaw(
            'LOWER('.$extract.') LIKE ?',
            [self::jsonPath($column), '%'.mb_strtolower(addcslashes($value, '%_\\')).'%']
        );
    }

    public function scopeSortBy(Builder $query, ?string $column, string $direction = 'asc'): Builder
    {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        if ($column === null || $column === '' || $column === 'row_number') {
            return $query->orderBy('row_number', $direction);
        }

        if (str_contains(mb_strtolower($column), 'tanggal')) {
            return $query->orderByRaw(
                'STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(row_data, ?)), \'%d-%m-%Y\') '.$direction,
                [self::jsonPath($column)],
            );
        }

        return $query->orderBy('row_data->'.$column, $direction);
    }

    public static function jsonPath(string $column): string
    {
        return '$."'.str_replace(['\\', '"'], ['\\\\', '\\"'], $column).'"';
    }
}
