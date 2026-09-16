<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiProviderUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'ai_provider_id',
        'organisation_id',
        'user_id',
        'project_id',
        'boq_id',
        'provider_key',
        'model',
        'operation',
        'input_units',
        'output_units',
        'estimated_cost',
        'duration_ms',
        'successful',
        'error_category',
        'error_message',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'input_units' => 'integer',
            'output_units' => 'integer',
            'estimated_cost' => 'decimal:6',
            'duration_ms' => 'integer',
            'successful' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
