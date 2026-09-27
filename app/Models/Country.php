<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $fillable = [
        'iso2',
        'name',
        'dial_code',
        'currency_code',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Active countries for dropdowns, keyed by ISO code.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return static::active()->orderBy('name')->pluck('name', 'iso2')->all();
    }
}
