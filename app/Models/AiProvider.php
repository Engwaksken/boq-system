<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'provider_type',
        'api_base_url',
        'default_model',
        'api_key',
        'organisation_id',
        'is_enabled',
        'is_default',
        'sort_order',
        'settings',
        'created_by',
        'updated_by',
        'last_tested_at',
        'last_test_status',
        'last_test_message',
    ];

    protected $hidden = [
        'api_key',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'settings' => 'encrypted:array',
            'is_enabled' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
            'last_tested_at' => 'datetime',
        ];
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function scopeForOrganisation(Builder $query, ?int $organisationId): Builder
    {
        return $query->where(function (Builder $q) use ($organisationId) {
            if ($organisationId) {
                $q->where('organisation_id', $organisationId);
            }

            $q->orWhereNull('organisation_id');
        });
    }
}
