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
            return $this->deny($request, 401, 'UNAUTHENTICATED', __('auth.unauthenticated'));
        }

        // Super Admin always satisfies role-gated administration routes.
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Normalise the two historic Super Admin spellings for callers that
        // still pass one of them explicitly.
        $normalisedRoles = collect($roles)
            ->flatMap(fn (string $role) => in_array($role, ['super-admin', 'super_admin'], true)
                ? ['super-admin', 'super_admin']
                : [$role])
            ->unique()
            ->values()
            ->all();

        if (! $user->hasAnyRole($normalisedRoles)) {
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
