<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
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
            'icons' => [
                ['src' => asset('icons/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('icons/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('icons/maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                ['name' => __('Projects'), 'url' => '/projects', 'icons' => [['src' => asset('icons/icon-192.png'), 'sizes' => '192x192']]],
                ['name' => __('BOQs'), 'url' => '/boqs', 'icons' => [['src' => asset('icons/icon-192.png'), 'sizes' => '192x192']]],
                ['name' => __('Get Prices'), 'url' => '/hardware-prices', 'icons' => [['src' => asset('icons/icon-192.png'), 'sizes' => '192x192']]],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600']);
    }

    public function serviceWorker(): Response
    {
        $assets = $this->buildAssets();
        // A new build gives the worker a new version, so old caches are replaced.
        $version = substr(sha1(implode('|', $assets).'|'.filemtime(__FILE__)), 0, 12);

        $script = view('pwa.service-worker', [
            'version' => $version,
            'precache' => array_values(array_unique([
                '/offline',
                '/icons/icon-192.png',
                '/icons/icon-512.png',
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
