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
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'UNAUTHENTICATED',
                    'message' => __('auth.unauthenticated'),
                ], 401);
            }

            abort(401, __('auth.unauthenticated'));
        }

        /*
         * Super Admin bypass.
         *
         * Super Admin should not depend on every individual permission
         * being manually attached.
         */
        if ($user->hasRole('super_admin')) {
            return $next($request);
        }

        /*
         * Normal permission enforcement.
         */
        if (! $user->hasAllPermissions($permissions)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'FORBIDDEN',
                    'message' => __('auth.forbidden'),
                ], 403);
            }

            abort(403, __('auth.forbidden'));
        }

        return $next($request);
    }
}