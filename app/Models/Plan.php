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
        'duration_hours',
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
            'duration_days' => 'integer',
            'duration_hours' => 'integer',
            'max_users' => 'integer',
            'max_projects' => 'integer',
            'max_boqs' => 'integer',
            'max_storage_bytes' => 'integer',
            'max_ai_credits' => 'integer',
            'max_ocr_pages' => 'integer',
            'max_translations' => 'integer',
            'trial_days' => 'integer',
            'grace_period_days' => 'integer',
            'display_order' => 'integer',
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
     * Record every price (or currency) change so subscribers can see the plan's
     * price history, and tell current subscribers when the price of their plan changes.
     */
    protected static function booted(): void
    {
        static::created(function (self $plan): void {
            $plan->priceChanges()->create([
                'old_price' => null,
                'new_price' => $plan->price ?? 0,
                'old_currency' => null,
                'new_currency' => $plan->currency ?: \App\Support\Regional::currency(),
                'changed_by' => auth()->id(),
                'changed_at' => now(),
            ]);
        });

        static::updated(function (self $plan): void {
            if (! $plan->wasChanged(['price', 'currency'])) {
                return;
            }

            $change = $plan->priceChanges()->create([
                'old_price' => $plan->getOriginal('price'),
                'new_price' => $plan->price ?? 0,
                'old_currency' => $plan->getOriginal('currency'),
                'new_currency' => $plan->currency ?: \App\Support\Regional::currency(),
                'changed_by' => auth()->id(),
                'changed_at' => now(),
            ]);

            \App\Jobs\NotifyPlanPriceChange::dispatch($change->id)->afterCommit();
        });
    }

    /**
     * Price history, newest first.
     */
    public function priceChanges(): HasMany
    {
        return $this->hasMany(PlanPriceChange::class)->orderByDesc('changed_at')->orderByDesc('id');
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
