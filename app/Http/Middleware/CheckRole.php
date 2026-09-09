<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'error_code' => 'UNAUTHENTICATED',
                'message' => __('auth.unauthenticated'),
            ], 401);
        }

        if (! $user->hasAnyRole($roles)) {
            return response()->json([
                'success' => false,
                'error_code' => 'FORBIDDEN',
                'message' => __('auth.forbidden'),
            ], 403);
        }

        return $next($request);
    }
}
