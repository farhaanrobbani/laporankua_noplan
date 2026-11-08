<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'output_format',
        'fields_json',
        'filters_json',
        'sorting_json',
        'layout_json',
        'is_default',
        'is_global',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'fields_json' => 'array',
            'filters_json' => 'array',
            'sorting_json' => 'array',
            'layout_json' => 'array',
            'is_default' => 'boolean',
            'is_global' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
