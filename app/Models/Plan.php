<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'description',
        'name_translations',
        'description_translations',
        'type',
        'duration_days',
        'price',
        'currency',
        'is_active',
        'is_archived',
        'has_trial',
        'trial_days',
        'max_users',
        'max_projects',
        'max_boqs',
        'max_storage_bytes',
        'max_ai_credits',
        'max_ocr_pages',
        'max_translations',
        'included_features',
        'included_updates',
        'feature_update_eligible',
        'auto_renewal',
        'grace_period_days',
        'refund_policy',
        'display_order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name_translations' => 'array',
            'description_translations' => 'array',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'is_archived' => 'boolean',
            'has_trial' => 'boolean',
            'included_features' => 'array',
            'included_updates' => 'array',
            'feature_update_eligible' => 'boolean',
            'auto_renewal' => 'boolean',
        ];
    }

    /**
     * Features included in this plan.
     */
    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'plan_feature')->withPivot('limits')->withTimestamps();
    }

    /**
     * Subscriptions for this plan.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Entitlements granted from this plan.
     */
    public function entitlements(): HasMany
    {
        return $this->hasMany(Entitlement::class);
    }

    /**
     * Check whether this plan includes a feature by code.
     */
    public function includesFeature(string $featureCode): bool
    {
        return $this->features()->where('code', $featureCode)->exists();
    }
}
