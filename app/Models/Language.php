<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'native_name',
        'direction',
        'date_format',
        'time_format',
        'number_format',
        'decimal_separator',
        'thousands_separator',
        'currency_format',
        'is_active',
        'is_default',
        'translation_completion',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'translation_completion' => 'decimal:2',
        ];
    }
}
