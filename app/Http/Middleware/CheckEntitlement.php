<?php

namespace App\Http\Middleware;

use App\Models\Entitlement;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckEntitlement
{
    /**
     * Handle an incoming request.
     *
     * Middleware signatures: entitlement:feature_code or
     * entitlement:feature_code,usage_limit_key
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $featureCode, ?string $usageLimitKey = null): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'error_code' => 'UNAUTHENTICATED',
                'message' => __('auth.unauthenticated'),
            ], 401);
        }

        // Super admin bypasses entitlement checks.
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $entitlements = Entitlement::query()
            ->where('status', 'active')
            ->where(function ($q) use ($user) {
                // A user entitlement is always applicable. Organisation
                // entitlements are applicable only when the user belongs to
                // that specific organisation; do not treat null as a match.
                $q->where('user_id', $user->id);

                if ($user->organisation_id !== null) {
                    $q->orWhere('organisation_id', $user->organisation_id);
                }
            })
            ->whereHas('feature', fn ($q) => $q->where('code', $featureCode))
            ->get()
            ->filter(fn (Entitlement $entitlement) => $entitlement->isValid());

        $selectedEntitlement = $usageLimitKey === null
            ? $entitlements->first()
            : $entitlements->first(
                fn (Entitlement $entitlement) => $entitlement->remainingFor($usageLimitKey) === null
                    || $entitlement->remainingFor($usageLimitKey) > 0
            );

        if (! $selectedEntitlement) {
            return response()->json([
                'success' => false,
                'error_code' => 'FEATURE_TOPUP_REQUIRED',
                'message' => __('subscriptions.feature_not_included'),
                'topup_options' => [],
            ], 403);
        }

        $response = $next($request);

        // Usage is consumed only for successful requests. Locking the row
        // keeps the read-modify-write of the JSON usage document atomic.
        if ($usageLimitKey !== null && $response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            DB::transaction(function () use ($selectedEntitlement, $usageLimitKey): void {
                $entitlement = Entitlement::query()
                    ->lockForUpdate()
                    ->find($selectedEntitlement->id);

                if (! $entitlement || ! $entitlement->isValid() || $entitlement->remainingFor($usageLimitKey) === null) {
                    return;
                }

                if ($entitlement->remainingFor($usageLimitKey) <= 0) {
                    return;
                }

                $usage = $entitlement->usage ?? [];
                $usage[$usageLimitKey] = (int) ($usage[$usageLimitKey] ?? 0) + 1;
                $entitlement->usage = $usage;
                $entitlement->save();
            });
        }

        return $response;
    }
}
