<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds baseline browser security headers to every response.
 *
 * A script/style Content-Security-Policy is intentionally not enforced: Livewire and
 * Alpine rely on inline scripts and runtime expression evaluation, Vite serves assets
 * from a dev server origin locally, and layouts pull styles from cdnjs and fonts.bunny.net.
 * The CSP sent here only restricts framing, <base> and plugins, which cannot break those.
 */
class SecurityHeaders
{
    public const PERMISSIONS_POLICY = 'camera=(self), microphone=(), geolocation=(), payment=(), usb=(), '
        .'clipboard-write=(self), publickey-credentials-get=(self), publickey-credentials-create=(self)';

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff', false);
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);

        if ($this->isSecure($request)) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains', false);
        }

        // Framing/permission headers only matter for documents rendered by a browser.
        if (! $request->is('api/*')) {
            $headers->set('X-Frame-Options', 'SAMEORIGIN', false);
            $headers->set('Permissions-Policy', self::PERMISSIONS_POLICY, false);
            if (! $headers->has('Content-Security-Policy')) {
                $headers->set('Content-Security-Policy', "frame-ancestors 'self'; base-uri 'self'; object-src 'none'");
            }
        }

        return $response;
    }

    private function isSecure(Request $request): bool
    {
        return $request->isSecure() || app()->environment('production');
    }
}
