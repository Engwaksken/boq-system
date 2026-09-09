<?php

namespace App\Http\Middleware;

use App\Models\Entitlement;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckEntitlement
{
    /**
     * Handle an incoming request.
     *
     * Middleware signature: entitlement:feature_code
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $featureCode): Response
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
        if ($user->hasRole('super-admin')) {
            return $next($request);
        }

        $hasEntitlement = Entitlement::query()
            ->where('status', 'active')
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('organisation_id', $user->organisation_id);
            })
            ->whereHas('feature', fn ($q) => $q->where('code', $featureCode))
            ->get()
            ->contains(fn (Entitlement $entitlement) => $entitlement->isValid());

        if (! $hasEntitlement) {
            return response()->json([
                'success' => false,
                'error_code' => 'FEATURE_TOPUP_REQUIRED',
                'message' => __('subscriptions.feature_not_included'),
                'feature' => $featureCode,
                'topup_options' => [],
            ], 403);
        }

        return $next($request);
    }
}
