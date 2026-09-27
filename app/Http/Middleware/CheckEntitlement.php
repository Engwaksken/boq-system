<?php

namespace App\Http\Middleware;

use App\Services\EntitlementGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckEntitlement
{
    public function __construct(private EntitlementGate $gate) {}

    /**
     * Handle an incoming request.
     *
     * Middleware signatures: entitlement:feature_code or
     * entitlement:feature_code,usage_limit_key
     *
     * API requests get JSON errors; web pages redirect to Subscriptions with a message.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $featureCode, ?string $usageLimitKey = null): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->wantsJson($request)
                ? response()->json([
                    'success' => false,
                    'error_code' => 'UNAUTHENTICATED',
                    'message' => __('auth.unauthenticated'),
                ], 401)
                : redirect()->guest(route('login'));
        }

        // Super admin bypasses entitlement checks.
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $selectedEntitlement = $this->gate->find($user, $featureCode, $usageLimitKey);

        if (! $selectedEntitlement) {
            if (! $this->wantsJson($request)) {
                return redirect()->route('subscriptions.index')->with(
                    'message',
                    'This feature needs an active subscription or top-up. Choose a plan to continue.'
                );
            }

            return response()->json([
                'success' => false,
                'error_code' => 'FEATURE_TOPUP_REQUIRED',
                'message' => __('subscriptions.feature_not_included'),
                'topup_options' => [],
            ], 403);
        }

        $response = $next($request);

        // Usage is consumed only for successful requests.
        if ($usageLimitKey !== null && $response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $this->gate->consume($selectedEntitlement, $usageLimitKey);
        }

        return $response;
    }

    private function wantsJson(Request $request): bool
    {
        return $request->expectsJson() || $request->is('api/*');
    }
}
