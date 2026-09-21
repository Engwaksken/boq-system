<?php

namespace App\Services;

use App\Models\Entitlement;
use App\Models\Feature;
use App\Models\Plan;
use App\Models\ProductVersion;
use App\Models\Subscription;
use App\Models\Topup;
use App\Models\TopupPurchase;
use App\Models\Transaction;
use App\Models\User;

class TopupService
{
    /**
     * Create a pending purchase record for a top-up before payment.
     */
    public function preparePurchase(Topup $topup, User $user, ?Subscription $subscription = null, ?int $organisationId = null): TopupPurchase
    {
        return TopupPurchase::create([
            'topup_id' => $topup->id,
            'user_id' => $user->id,
            'organisation_id' => $organisationId ?? $user->organisation_id ?? $subscription?->organisation_id,
            'subscription_id' => $subscription?->id,
            'status' => 'pending',
            'purchased_at' => now(),
            'version' => $this->currentProductVersion(),
        ]);
    }

    /**
     * Activate a purchase and grant its entitlements, idempotently.
     */
    public function activate(TopupPurchase $purchase): TopupPurchase
    {
        if ($purchase->status === 'active') {
            return $purchase->fresh(['topup']);
        }

        $topup = $purchase->topup;

        $purchase->update([
            'status' => 'active',
            'activated_at' => now(),
            'expires_at' => $this->expiresAt($topup, $purchase),
            'is_permanent' => $topup->is_permanent || $topup->duration_days === null,
            'version' => $topup->release_version ?? $purchase->version,
        ]);

        $this->grantIncludedFeatures($purchase, $topup);
        $this->grantUsageCredits($purchase, $topup);
        $this->bumpSubscriptionVersion($purchase, $topup);

        return $purchase->fresh(['topup']);
    }

    /**
     * Activate the top-up behind a settled payment transaction.
     */
    public function activateForTransaction(Transaction $transaction): ?TopupPurchase
    {
        if ($transaction->product_type !== 'topup') {
            return null;
        }

        $topup = Topup::find($transaction->product_id);
        if (! $topup) {
            return null;
        }

        $purchase = TopupPurchase::firstOrNew(['transaction_id' => $transaction->id]);
        if (! $purchase->exists) {
            $purchase->fill([
                'topup_id' => $topup->id,
                'user_id' => $transaction->user_id,
                'organisation_id' => $transaction->organisation_id,
                'subscription_id' => $transaction->subscription_id,
                'status' => 'pending',
                'purchased_at' => $transaction->created_at ?? now(),
                'version' => $this->currentProductVersion(),
            ])->save();
        }

        return $this->activate($purchase);
    }

    /**
     * How many times a user has purchased the given top-up.
     */
    public function purchasedCount(Topup $topup, User $user, array $statuses = ['pending', 'active']): int
    {
        return TopupPurchase::query()
            ->where('topup_id', $topup->id)
            ->where('user_id', $user->id)
            ->whereIn('status', $statuses)
            ->count();
    }

    /**
     * Whether a user may still purchase the given top-up.
     */
    public function purchasableBy(Topup $topup, User $user, ?Plan $plan = null): bool
    {
        if (! $topup->purchasable($plan?->code)) {
            return false;
        }

        $limit = $topup->purchase_limit;

        return $limit === null || $this->purchasedCount($topup, $user) < (int) $limit;
    }

    /**
     * Grant one entitlement per feature code listed on the top-up.
     */
    protected function grantIncludedFeatures(TopupPurchase $purchase, Topup $topup): void
    {
        $featureCodes = $topup->included_features ?? [];

        if (empty($featureCodes)) {
            return;
        }

        $features = Feature::whereIn('code', $featureCodes)->get();

        foreach ($features as $feature) {
            $this->grantEntitlement($purchase, $topup, $feature, $topup->limits ?? null);
        }
    }

    /**
     * Grant credit entitlements for usage-based top-ups.
     */
    protected function grantUsageCredits(TopupPurchase $purchase, Topup $topup): void
    {
        $credits = $topup->usage_credits ?? [];

        foreach ($credits as $key => $value) {
            $feature = $this->ensureCreditFeature(Topup::creditFeatureCode((string) $key), (string) $key);
            $this->grantEntitlement($purchase, $topup, $feature, [$key => $value]);
        }
    }

    /**
     * Grant or refresh an entitlement for a single feature from a top-up.
     */
    protected function grantEntitlement(TopupPurchase $purchase, Topup $topup, Feature $feature, ?array $limits): Entitlement
    {
        $expiresAt = $this->expiresAt($topup, $purchase);
        $isPermanent = $topup->is_permanent || $topup->duration_days === null;

        return Entitlement::updateOrCreate(
            [
                'user_id' => $purchase->user_id,
                'organisation_id' => $purchase->organisation_id,
                'feature_id' => $feature->id,
                'source' => 'topup',
                'topup_purchase_id' => $purchase->id,
            ],
            [
                'subscription_id' => $purchase->subscription_id,
                'plan_id' => null,
                'status' => 'active',
                'granted_at' => now(),
                'expires_at' => $expiresAt,
                'is_permanent' => $isPermanent,
                'limits' => $limits,
                'usage' => [],
                'metadata' => [
                    'topup_code' => $topup->code,
                    'topup_type' => $topup->type,
                ],
            ]
        );
    }

    /**
     * Bump the linked subscription's product version for update top-ups.
     */
    protected function bumpSubscriptionVersion(TopupPurchase $purchase, Topup $topup): void
    {
        $releaseVersion = $topup->release_version;
        $subscription = $purchase->subscription;

        if (! $subscription || ! $releaseVersion || ! $topup->isFeatureUpdate()) {
            return;
        }

        if ($subscription->product_version === null || version_compare($releaseVersion, $subscription->product_version, '>')) {
            $subscription->product_version = $releaseVersion;
            $subscription->save();
        }
    }

    /**
     * Resolve the expiry for a purchase, from the top-up duration.
     */
    protected function expiresAt(Topup $topup, TopupPurchase $purchase): ?\Illuminate\Support\Carbon
    {
        if ($topup->is_permanent || $topup->duration_days === null) {
            return null;
        }

        return ($purchase->activated_at ?? now())->copy()->addDays((int) $topup->duration_days);
    }

    /**
     * Resolve the pinned product version for the platform.
     */
    protected function currentProductVersion(): string
    {
        $version = ProductVersion::where('is_active', true)->orderByDesc('release_date')->first();

        return $version?->version_number ?? '1.0';
    }

    /**
     * Ensure the credit feature backing a usage credit top-up exists.
     */
    protected function ensureCreditFeature(string $code, string $usageKey): Feature
    {
        return Feature::firstOrCreate(
            ['code' => $code],
            [
                'name' => 'Credits: '.($usageKey ?? $code),
                'module' => 'credits',
                'version_introduced' => '1.0',
                'is_active' => true,
                'requires_topup' => true,
                'usage_limits' => [$usageKey => 0],
            ]
        );
    }
}