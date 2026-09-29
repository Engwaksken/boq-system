<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use App\Support\PwaIcons;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Progressive Web App: the manifest (so the site can be installed on phones
 * and desktops), the service worker and the offline page.
 */
class PwaController extends Controller
{
    public const THEME_COLOR = '#05645b';

    public function manifest(): JsonResponse
    {
        $name = SiteSetting::get('system_name', 'BOQ System') ?: 'BOQ System';

        return response()->json([
            'id' => '/',
            'name' => $name,
            'short_name' => mb_strimwidth($name, 0, 12, ''),
            'description' => __('Bills of quantities, market prices and project costs.'),
            'start_url' => '/dashboard?source=pwa',
            'scope' => '/',
            'display' => 'standalone',
            'display_override' => ['window-controls-overlay', 'standalone'],
            'orientation' => 'any',
            'background_color' => '#ffffff',
            'theme_color' => self::THEME_COLOR,
            'categories' => ['business', 'productivity'],
            'lang' => app()->getLocale(),
            // Made from the system logo (Admin > Settings), else the built-in icons.
            'icons' => [
                ['src' => PwaIcons::url('icon-192'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => PwaIcons::url('icon-512'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => PwaIcons::url('maskable-512'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                ['name' => __('Projects'), 'url' => '/projects', 'icons' => [['src' => PwaIcons::url('icon-192'), 'sizes' => '192x192']]],
                ['name' => __('BOQs'), 'url' => '/boqs', 'icons' => [['src' => PwaIcons::url('icon-192'), 'sizes' => '192x192']]],
                ['name' => __('Get Prices'), 'url' => '/hardware-prices', 'icons' => [['src' => PwaIcons::url('icon-192'), 'sizes' => '192x192']]],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'no-cache']);
    }

    /** App icon drawn from the system logo; the URL carries a version, so it can be cached for long. */
    public function icon(string $variant): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return response()->file(PwaIcons::path($variant), [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    public function serviceWorker(): Response
    {
        $assets = $this->buildAssets();
        // A new build gives the worker a new version, so old caches are replaced.
        $version = substr(sha1(implode('|', $assets).'|'.filemtime(__FILE__).'|'.PwaIcons::url('icon-192')), 0, 12);

        $script = view('pwa.service-worker', [
            'version' => $version,
            'precache' => array_values(array_unique([
                '/offline',
                parse_url(PwaIcons::url('icon-192'), PHP_URL_PATH).(parse_url(PwaIcons::url('icon-192'), PHP_URL_QUERY) ? '?'.parse_url(PwaIcons::url('icon-192'), PHP_URL_QUERY) : ''),
                ...$assets,
            ])),
        ])->render();

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Service-Worker-Allowed' => '/',
        ]);
    }

    public function offline(): Response
    {
        return response()->view('pwa.offline', [
            'name' => SiteSetting::get('system_name', 'BOQ System') ?: 'BOQ System',
            'icon' => PwaIcons::url('icon-192'),
        ]);
    }

    /** @return list<string> the compiled CSS/JS files of the current build */
    private function buildAssets(): array
    {
        $manifest = public_path('build/manifest.json');
        if (! is_file($manifest)) {
            return [];
        }

        $entries = json_decode((string) file_get_contents($manifest), true) ?: [];
        $files = [];
        foreach ($entries as $entry) {
            foreach (array_merge([$entry['file'] ?? null], $entry['css'] ?? []) as $file) {
                if ($file) {
                    $files[] = '/build/'.$file;
                }
            }
        }

        return $files;
    }
}
