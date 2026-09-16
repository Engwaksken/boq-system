<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(
        Request $request,
        Closure $next,
        string ...$roles
    ): Response {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'error_code' => 'UNAUTHENTICATED',
                'message' => __('auth.unauthenticated'),
            ], 401);
        }

        /*
         * Super Admin bypass.
         *
         * The system historically used both:
         *   super-admin
         *   super_admin
         *
         * Treat either form as the same privileged role.
         */
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $normalisedRoles = collect($roles)
            ->flatMap(function (string $role) {
                $role = strtolower(trim($role));

                return array_unique([
                    $role,
                    str_replace('_', '-', $role),
                    str_replace('-', '_', $role),
                ]);
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (! $user->hasAnyRole($normalisedRoles)) {
            return response()->json([
                'success' => false,
                'error_code' => 'FORBIDDEN',
                'message' => __('auth.forbidden'),
            ], 403);
        }

        return $next($request);
    }
}