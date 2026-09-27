<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = [
        'code',
        'name',
        'symbol',
        'decimal_places',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'decimal_places' => 'integer',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Active currency codes for form dropdowns, default first.
     *
     * @return list<string>
     */
    public static function activeCodes(): array
    {
        return static::active()
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('code')
            ->pluck('code')
            ->all();
    }
}
