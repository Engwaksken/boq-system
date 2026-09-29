<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_describes_an_installable_app(): void
    {
        $response = $this->get('/manifest.webmanifest')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json');

        $manifest = $response->json();
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertNotEmpty($manifest['name']);
        $this->assertContains('maskable', array_column($manifest['icons'], 'purpose'));

        foreach ($manifest['icons'] as $icon) {
            $path = public_path(ltrim(parse_url($icon['src'], PHP_URL_PATH), '/'));
            $this->assertFileExists($path);
            [$width] = getimagesize($path);
            $this->assertSame((int) explode('x', $icon['sizes'])[0], $width);
        }
    }

    public function test_service_worker_is_served_from_the_root_with_the_offline_page(): void
    {
        $response = $this->get('/sw.js')
            ->assertOk()
            ->assertHeader('Service-Worker-Allowed', '/');

        $this->assertStringContainsString('application/javascript', $response->headers->get('Content-Type'));
        $script = $response->getContent();
        $this->assertStringContainsString("const OFFLINE_URL = '/offline'", $script);
        $this->assertStringContainsString('/icons/icon-192.png', $script);
        // Private requests are never cached.
        $this->assertStringContainsString('livewire', $script);
        $this->assertStringContainsString("request.method !== 'GET'", $script);
    }

    public function test_offline_page_works_without_signing_in(): void
    {
        $this->get('/offline')->assertOk()->assertSee('You are offline')->assertDontSee('/build/', false);
    }

    public function test_pages_link_the_manifest_and_offer_the_install_button(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('data-pwa-install', false)
            ->assertSee('data-pwa-ios-help', false);

        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('apple-touch-icon', false)
            ->assertSee('data-pwa-install', false)
            ->assertSee('Install app');
    }

    public function test_app_icons_are_made_from_the_system_logo(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        // A wide logo drawn on a solid blue background.
        $logo = imagecreatetruecolor(400, 200);
        imagefill($logo, 0, 0, imagecolorallocate($logo, 20, 60, 200));
        imagefilledrectangle($logo, 150, 60, 250, 140, imagecolorallocate($logo, 255, 255, 255));
        ob_start();
        imagepng($logo);
        \Illuminate\Support\Facades\Storage::disk('public')->put('site/logo-test.png', ob_get_clean());
        \App\Models\SiteSetting::set('logo', 'site/logo-test.png');

        $icons = collect($this->get('/manifest.webmanifest')->assertOk()->json('icons'));
        $maskable = $icons->firstWhere('purpose', 'maskable');
        $this->assertStringContainsString('/pwa/icons/maskable-512.png?v=', $maskable['src']);

        $response = $this->get(parse_url($maskable['src'], PHP_URL_PATH).'?'.parse_url($maskable['src'], PHP_URL_QUERY))->assertOk();
        $png = $response->getFile()->getContent();
        [$width, $height] = getimagesizefromstring($png);
        $this->assertSame([512, 512], [$width, $height]);

        // The logo's own background fills the icon, and the logo sits in the middle.
        $icon = imagecreatefromstring($png);
        $corner = imagecolorsforindex($icon, imagecolorat($icon, 2, 2));
        $this->assertSame([20, 60, 200], [$corner['red'], $corner['green'], $corner['blue']]);
        $centre = imagecolorsforindex($icon, imagecolorat($icon, 256, 256));
        $this->assertSame(255, $centre['red']);

        $this->get('/login')->assertSee('/pwa/icons/apple-touch-icon.png?v=', false);

        // Without a logo the built-in icons are used.
        \App\Models\SiteSetting::set('logo', '');
        $this->assertStringEndsWith('/icons/icon-192.png', \App\Support\PwaIcons::url('icon-192'));
    }
}
