<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out accounts an administrator has disabled, on both web sessions and API tokens.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->is_active) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'success' => false,
                'error_code' => 'ACCOUNT_DISABLED',
                'message' => 'Your account has been disabled. Contact your administrator.',
            ], 403);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors([
            'email' => 'Your account has been disabled. Contact your administrator.',
        ]);
    }
}
