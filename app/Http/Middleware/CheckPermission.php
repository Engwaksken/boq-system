<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->deny($request, 401, 'UNAUTHENTICATED', __('auth.unauthenticated'));
        }

        // One canonical Super Admin check supports both legacy slugs.
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        if (! $user->hasAllPermissions($permissions)) {
            return $this->deny($request, 403, 'FORBIDDEN', __('auth.forbidden'));
        }

        return $next($request);
    }

    private function deny(Request $request, int $status, string $code, string $message): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'error_code' => $code,
                'message' => $message,
            ], $status);
        }

        abort($status, $message);
    }
}
