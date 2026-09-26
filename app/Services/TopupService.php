<?php

declare(strict_types=1);

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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TopupService
{
    /**
     * Create a pending top-up purchase.
     */
    public function preparePurchase(
        Topup $topup,
        User $user,
        ?Subscription $subscription = null,
        ?int $organisationId = null
    ): TopupPurchase {
        return TopupPurchase::create([
            'topup_id' =>
                $topup->id,

            'user_id' =>
                $user->id,

            'organisation_id' =>
                $organisationId
                ?? $user->organisation_id
                ?? $subscription?->organisation_id,

            'subscription_id' =>
                $subscription?->id,

            'status' =>
                'pending',

            'purchased_at' =>
                now(),

            'version' =>
                $this->currentProductVersion(),
        ]);
    }

    /**
     * Activate a top-up purchase.
     *
     * This method is deliberately idempotent.
     */
    public function activate(
        TopupPurchase $purchase
    ): TopupPurchase {
        return DB::transaction(
            function () use (
                $purchase
            ): TopupPurchase {
                /*
                 * Lock the purchase so two callbacks or requests
                 * cannot activate it simultaneously.
                 */
                $lockedPurchase =
                    TopupPurchase::query()
                        ->with([
                            'topup',
                            'subscription',
                        ])
                        ->lockForUpdate()
                        ->findOrFail(
                            $purchase->getKey()
                        );

                $topup =
                    $lockedPurchase->topup;

                /*
                 * Even when already active, re-running the grant
                 * methods is safe because entitlement creation uses
                 * updateOrCreate().
                 *
                 * This also repairs an older active purchase that
                 * may have missed its entitlement.
                 */
                if (
                    $lockedPurchase->status
                    !== 'active'
                ) {
                    $activatedAt =
                        $lockedPurchase
                            ->activated_at
                        ?? now();

                    $lockedPurchase->forceFill([
                        'status' =>
                            'active',

                        'activated_at' =>
                            $activatedAt,

                        'expires_at' =>
                            $this->expiresAt(
                                $topup,
                                $lockedPurchase,
                                $activatedAt
                            ),

                        'is_permanent' =>
                            $topup->is_permanent
                            || $topup->duration_days === null,

                        'version' =>
                            $topup->release_version
                            ?? $lockedPurchase->version,
                    ])->save();
                }

                $this->grantIncludedFeatures(
                    $lockedPurchase,
                    $topup
                );

                $this->grantUsageCredits(
                    $lockedPurchase,
                    $topup
                );

                $this->bumpSubscriptionVersion(
                    $lockedPurchase,
                    $topup
                );

                return $lockedPurchase
                    ->fresh([
                        'topup',
                        'subscription',
                    ]);
            }
        );
    }

    /**
     * Activate a top-up after a successful payment.
     */
    public function activateForTransaction(
        Transaction $transaction
    ): ?TopupPurchase {
        if (
            $transaction->product_type
            !== 'topup'
        ) {
            return null;
        }

        $topup =
            Topup::query()
                ->find(
                    $transaction->product_id
                );

        if (! $topup) {
            return null;
        }

        $purchase =
            TopupPurchase::query()
                ->firstOrNew([
                    'transaction_id' =>
                        $transaction->id,
                ]);

        if (! $purchase->exists) {
            $purchase->fill([
                'topup_id' =>
                    $topup->id,

                'user_id' =>
                    $transaction->user_id,

                'organisation_id' =>
                    $transaction->organisation_id,

                'subscription_id' =>
                    $transaction->subscription_id,

                'status' =>
                    'pending',

                'purchased_at' =>
                    $transaction->created_at
                    ?? now(),

                'version' =>
                    $this->currentProductVersion(),
            ]);

            $purchase->save();
        }

        return $this->activate(
            $purchase
        );
    }

    /**
     * Number of matching purchases.
     */
    public function purchasedCount(
        Topup $topup,
        User $user,
        array $statuses = [
            'pending',
            'active',
        ]
    ): int {
        return TopupPurchase::query()
            ->where(
                'topup_id',
                $topup->id
            )
            ->where(
                'user_id',
                $user->id
            )
            ->whereIn(
                'status',
                $statuses
            )
            ->count();
    }

    /**
     * Check purchase limit.
     */
    public function purchasableBy(
        Topup $topup,
        User $user,
        ?Plan $plan = null
    ): bool {
        if (
            ! $topup->purchasable(
                $plan?->code
            )
        ) {
            return false;
        }

        $limit =
            $topup->purchase_limit;

        return $limit === null
            || $this->purchasedCount(
                $topup,
                $user
            ) < (int) $limit;
    }

    /**
     * Grant features bundled with a top-up.
     */
    protected function grantIncludedFeatures(
        TopupPurchase $purchase,
        Topup $topup
    ): void {
        $featureCodes =
            collect(
                $topup->included_features
                ?? []
            )
                ->filter(
                    fn ($code) =>
                        is_string($code)
                        && trim($code) !== ''
                )
                ->map(
                    fn ($code) =>
                        trim($code)
                )
                ->unique()
                ->values();

        if ($featureCodes->isEmpty()) {
            return;
        }

        foreach (
            $featureCodes
            as $featureCode
        ) {
            /*
             * A top-up should not silently fail merely because
             * its backing feature has not yet been seeded.
             *
             * firstOrCreate also makes this race-safe at the
             * application level when the code has a unique index.
             */
            $feature =
                Feature::query()
                    ->firstOrCreate(
                        [
                            'code' =>
                                $featureCode,
                        ],
                        [
                            'name' =>
                                $this->featureName(
                                    $featureCode
                                ),

                            'module' =>
                                'topups',

                            'version_introduced' =>
                                '1.0',

                            'is_active' =>
                                true,

                            'requires_topup' =>
                                true,
                        ]
                    );

            $this->grantEntitlement(
                $purchase,
                $topup,
                $feature,
                $topup->limits
                ?? null
            );
        }
    }

    /**
     * Grant usage-credit entitlements.
     */
    protected function grantUsageCredits(
        TopupPurchase $purchase,
        Topup $topup
    ): void {
        $credits =
            $topup->usage_credits
            ?? [];

        foreach (
            $credits
            as $key => $value
        ) {
            $feature =
                $this->ensureCreditFeature(
                    Topup::creditFeatureCode(
                        (string) $key
                    ),
                    (string) $key
                );

            $this->grantEntitlement(
                $purchase,
                $topup,
                $feature,
                [
                    $key => $value,
                ]
            );
        }
    }

    /**
     * Grant or refresh one top-up entitlement.
     */
    protected function grantEntitlement(
        TopupPurchase $purchase,
        Topup $topup,
        Feature $feature,
        ?array $limits
    ): Entitlement {
        $expiresAt =
            $this->expiresAt(
                $topup,
                $purchase,
                $purchase->activated_at
                ?? now()
            );

        $isPermanent =
            $topup->is_permanent
            || $topup->duration_days === null;

        return Entitlement::query()
            ->updateOrCreate(
                [
                    'user_id' =>
                        $purchase->user_id,

                    'organisation_id' =>
                        $purchase->organisation_id,

                    'feature_id' =>
                        $feature->id,

                    'source' =>
                        'topup',

                    'topup_purchase_id' =>
                        $purchase->id,
                ],
                [
                    'subscription_id' =>
                        $purchase->subscription_id,

                    'plan_id' =>
                        null,

                    'status' =>
                        'active',

                    'granted_at' =>
                        now(),

                    'expires_at' =>
                        $expiresAt,

                    'is_permanent' =>
                        $isPermanent,

                    'limits' =>
                        $limits,

                    /*
                     * Do not accumulate usage on repeated activation.
                     * This represents a top-up purchase entitlement.
                     */
                    'usage' =>
                        [],

                    'metadata' => [
                        'topup_code' =>
                            $topup->code,

                        'topup_type' =>
                            $topup->type,
                    ],
                ]
            );
    }

    /**
     * Update subscription product version when applicable.
     */
    protected function bumpSubscriptionVersion(
        TopupPurchase $purchase,
        Topup $topup
    ): void {
        $releaseVersion =
            $topup->release_version;

        $subscription =
            $purchase->subscription;

        if (
            ! $subscription
            || ! $releaseVersion
            || ! $topup->isFeatureUpdate()
        ) {
            return;
        }

        if (
            $subscription->product_version === null
            || version_compare(
                $releaseVersion,
                $subscription->product_version,
                '>'
            )
        ) {
            $subscription->product_version =
                $releaseVersion;

            $subscription->save();
        }
    }

    /**
     * Resolve entitlement expiry.
     */
    protected function expiresAt(
        Topup $topup,
        TopupPurchase $purchase,
        ?Carbon $activatedAt = null
    ): ?Carbon {
        if (
            $topup->is_permanent
            || $topup->duration_days === null
        ) {
            return null;
        }

        $base =
            $activatedAt
            ?? $purchase->activated_at
            ?? now();

        return $base
            ->copy()
            ->addDays(
                (int) $topup->duration_days
            );
    }

    /**
     * Current platform version.
     */
    protected function currentProductVersion(): string
    {
        $version =
            ProductVersion::query()
                ->where(
                    'is_active',
                    true
                )
                ->orderByDesc(
                    'release_date'
                )
                ->first();

        return $version?->version_number
            ?? '1.0';
    }

    /**
     * Ensure usage-credit feature exists.
     */
    protected function ensureCreditFeature(
        string $code,
        string $usageKey
    ): Feature {
        return Feature::query()
            ->firstOrCreate(
                [
                    'code' => $code,
                ],
                [
                    'name' =>
                        'Credits: '
                        .$this->featureName(
                            $usageKey
                        ),

                    'module' =>
                        'credits',

                    'version_introduced' =>
                        '1.0',

                    'is_active' =>
                        true,

                    'requires_topup' =>
                        true,

                    'usage_limits' => [
                        $usageKey => 0,
                    ],
                ]
            );
    }

    /**
     * Convert feature code into a readable name.
     */
    protected function featureName(
        string $code
    ): string {
        return ucwords(
            str_replace(
                [
                    '.',
                    '_',
                    '-',
                ],
                ' ',
                $code
            )
        );
    }
}