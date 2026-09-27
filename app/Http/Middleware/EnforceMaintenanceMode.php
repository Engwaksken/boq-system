<?php

namespace App\Http\Middleware;

use App\Models\SiteSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Admin > Settings > "Enable maintenance mode": everyone except super admins sees a
 * maintenance notice. Sign-in, health checks, mobile config and payment webhooks keep
 * working so admins can log in and in-flight payments still settle.
 */
class EnforceMaintenanceMode
{
    private const ALWAYS_ALLOWED = [
        'login', 'logout', 'webauthn/*', 'up', 'health',
        'api/v1/auth/login', 'api/v1/auth/logout', 'api/v1/mobile-config', 'api/v1/payment-webhooks/*',
        'livewire/*', 'build/*', 'storage/*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->enabled() || $request->is(...self::ALWAYS_ALLOWED) || $request->user()?->isSuperAdmin()) {
            return $next($request);
        }

        $message = 'The system is undergoing scheduled maintenance. Please try again shortly.';

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'error_code' => 'MAINTENANCE_MODE',
                'message' => $message,
            ], 503, ['Retry-After' => '600']);
        }

        return response()->view('errors.maintenance', ['message' => $message], 503, ['Retry-After' => '600']);
    }

    private function enabled(): bool
    {
        try {
            return (bool) SiteSetting::get('maintenance_mode', false);
        } catch (Throwable) {
            return false;
        }
    }
}
