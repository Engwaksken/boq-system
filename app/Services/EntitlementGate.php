<?php

namespace App\Services;

use App\Models\Entitlement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Single place that decides whether a user may use a paid feature, shared by the
 * API middleware and web (Livewire) actions so both enforce the same subscription rules.
 */
class EntitlementGate
{
    /**
     * The entitlement that grants the feature (with usage left, when a usage key is given).
     * Super admins get a synthetic pass (null entitlement but allowed) via allows().
     */
    public function find(User $user, string $featureCode, ?string $usageLimitKey = null): ?Entitlement
    {
        $entitlements = Entitlement::query()
            ->where('status', 'active')
            ->where(function ($query) use ($user) {
                // User entitlements always apply; organisation entitlements only for
                // members of that organisation (null never matches null).
                $query->where('user_id', $user->id);

                if ($user->organisation_id !== null) {
                    $query->orWhere('organisation_id', $user->organisation_id);
                }
            })
            ->whereHas('feature', fn ($query) => $query->where('code', $featureCode))
            ->get()
            ->filter(fn (Entitlement $entitlement) => $entitlement->isValid());

        return $usageLimitKey === null
            ? $entitlements->first()
            : $entitlements->first(
                fn (Entitlement $entitlement) => $entitlement->remainingFor($usageLimitKey) === null
                    || $entitlement->remainingFor($usageLimitKey) > 0
            );
    }

    public function allows(User $user, string $featureCode, ?string $usageLimitKey = null): bool
    {
        return $user->isSuperAdmin() || $this->find($user, $featureCode, $usageLimitKey) !== null;
    }

    /**
     * Count one use of a limited feature. Row-locked so concurrent requests can't overspend.
     */
    public function consume(Entitlement $entitlement, string $usageLimitKey): void
    {
        DB::transaction(function () use ($entitlement, $usageLimitKey): void {
            $locked = Entitlement::query()->lockForUpdate()->find($entitlement->id);

            if (! $locked || ! $locked->isValid() || $locked->remainingFor($usageLimitKey) === null) {
                return;
            }

            if ($locked->remainingFor($usageLimitKey) <= 0) {
                return;
            }

            $usage = $locked->usage ?? [];
            $usage[$usageLimitKey] = (int) ($usage[$usageLimitKey] ?? 0) + 1;
            $locked->usage = $usage;
            $locked->save();
        });
    }
}
