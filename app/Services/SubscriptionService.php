<?php

namespace App\Services;

use App\Models\Entitlement;
use App\Models\Feature;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;

class SubscriptionService
{
    /**
     * Activate a subscription and grant its entitlements.
     */
    public function activate(Subscription $subscription): Subscription
    {
        $plan = $subscription->plan;

        $subscription->status = 'active';
        $subscription->payment_status = 'paid';
        $subscription->start_date = $subscription->start_date ?? now();

        if ($plan->type === 'lifetime') {
            $subscription->end_date = null;
            $subscription->access_type = 'lifetime';
        } else {
            $durationDays = $plan->duration_days ?? $this->defaultDurationForType($plan->type);
            $subscription->end_date = $subscription->start_date->copy()->addDays($durationDays);
            $subscription->renewal_date = $subscription->end_date->copy();
            $subscription->access_type = $plan->type;
        }

        if ($plan->grace_period_days > 0) {
            $subscription->grace_period_end_date = $subscription->end_date?->copy()->addDays($plan->grace_period_days);
        }

        $subscription->product_version = $subscription->product_version ?? $this->currentProductVersion();
        $subscription->save();

        $this->grantPlanEntitlements($subscription);

        return $subscription->fresh();
    }

    /**
     * Grant entitlements for all features included in the subscription's plan.
     */
    public function grantPlanEntitlements(Subscription $subscription): void
    {
        $plan = $subscription->plan;

        // Load all features with their pivot limits in a single query to avoid N+1.
        $features = $plan->features()->withPivot('limits')->get();

        foreach ($features as $feature) {
            $this->grantEntitlement($subscription, $feature, $feature->pivot?->limits);
        }
    }

    /**
     * Grant a single feature entitlement from a subscription's plan.
     */
    public function grantEntitlement(Subscription $subscription, Feature $feature, mixed $limits = null): Entitlement
    {
        $plan = $subscription->plan;
        $limits ??= $plan->features()->where('features.id', $feature->id)->first()?->pivot?->limits;

        $expiresAt = $subscription->end_date;
        $isPermanent = $plan->type === 'lifetime';

        return Entitlement::updateOrCreate(
            [
                'user_id' => $subscription->user_id,
                'organisation_id' => $subscription->organisation_id,
                'feature_id' => $feature->id,
                'subscription_id' => $subscription->id,
            ],
            [
                'plan_id' => $plan->id,
                'source' => 'plan',
                'status' => 'active',
                'granted_at' => now(),
                'expires_at' => $expiresAt,
                'is_permanent' => $isPermanent,
                'limits' => $limits,
                'usage' => [],
            ]
        );
    }

    /**
     * Check whether a user has a valid entitlement for a feature code.
     */
    public function hasFeature(User $user, string $featureCode): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return Entitlement::query()
            ->where('status', 'active')
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('organisation_id', $user->organisation_id);
            })
            ->whereHas('feature', fn ($q) => $q->where('code', $featureCode))
            ->get()
            ->contains(fn (Entitlement $entitlement) => $entitlement->isValid());
    }

    /**
     * Get the current active subscription for a user or organisation.
     */
    public function currentSubscription(?User $user = null, ?int $organisationId = null): ?Subscription
    {
        return Subscription::query()
            ->where(function ($q) use ($user, $organisationId) {
                if ($user) {
                    $q->where('user_id', $user->id);
                }
                if ($organisationId) {
                    $q->orWhere('organisation_id', $organisationId);
                }
            })
            ->whereIn('status', ['active', 'trial', 'grace_period'])
            ->latest()
            ->first();
    }

    /**
     * Get the default duration in days for a plan type.
     */
    protected function defaultDurationForType(string $type): int
    {
        return match ($type) {
            'monthly' => 30,
            'three_month' => 90,
            'six_month' => 180,
            'annual' => 365,
            'one_time' => 365,
            'lifetime' => 0,
            default => 30,
        };
    }

    /**
     * Get the current product version string.
     */
    protected function currentProductVersion(): string
    {
        $version = \App\Models\ProductVersion::where('is_active', true)
            ->orderByDesc('release_date')
            ->first();

        return $version?->version_number ?? '1.0';
    }
}
