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

        $jsonText = $query->getModel()->getConnection()->getDriverName() === 'pgsql'
            ? 'row_data::text'
            : 'row_data';

        return $query->whereRaw(
            'LOWER('.$jsonText.') LIKE ?',
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

        $driver = $query->getModel()->getConnection()->getDriverName();

        $extract = match ($driver) {
            'mysql', 'mariadb' => 'JSON_UNQUOTE(JSON_EXTRACT(row_data, ?))',
            'pgsql' => 'row_data ->> ?',
            default => 'json_extract(row_data, ?)',
        };

        $path = $driver === 'pgsql' ? $column : self::jsonPath($column);

        return $query->whereRaw(
            'LOWER('.$extract.') LIKE ?',
            [$path, '%'.mb_strtolower(addcslashes($value, '%_\\')).'%']
        );
    }

    public function scopeSortBy(Builder $query, ?string $column, string $direction = 'asc'): Builder
    {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        if ($column === null || $column === '' || $column === 'row_number') {
            return $query->orderBy('row_number', $direction);
        }

        $driver = $query->getModel()->getConnection()->getDriverName();

        if (str_contains(mb_strtolower($column), 'tanggal')) {
            if ($driver === 'pgsql') {
                $expression = <<<'SQL'
CASE
    WHEN (row_data ->> ?) ~ '^[0-9]{1,2}-[0-9]{1,2}-[0-9]{4}$' THEN to_date(row_data ->> ?, 'DD-MM-YYYY')
    WHEN (row_data ->> ?) ~ '^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}$' THEN to_date(row_data ->> ?, 'YYYY-MM-DD')
    ELSE NULL
END
SQL;

                return $query->orderByRaw(
                    $expression.' '.$direction.' NULLS LAST',
                    [$column, $column, $column, $column],
                );
            }

            return $query->orderByRaw(
                'STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(row_data, ?)), \'%d-%m-%Y\') '.$direction,
                [self::jsonPath($column)],
            );
        }

        if ($driver === 'pgsql') {
            return $query->orderByRaw(
                'LOWER(row_data ->> ?) '.$direction.' NULLS LAST',
                [$column],
            );
        }

        return $query->orderBy('row_data->'.$column, $direction);
    }

    public static function jsonPath(string $column): string
    {
        return '$."'.str_replace(['\\', '"'], ['\\\\', '\\"'], $column).'"';
    }
}
