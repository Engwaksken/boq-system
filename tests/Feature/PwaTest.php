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
}
