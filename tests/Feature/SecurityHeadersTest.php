<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_pages_send_security_headers(): void
    {
        $response = $this->get('http://localhost/login');

        $response->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeaderMissing('Strict-Transport-Security');

        $permissions = $response->headers->get('Permissions-Policy');
        $this->assertStringContainsString('camera=(self)', $permissions);
        $this->assertStringContainsString('publickey-credentials-get=(self)', $permissions);

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        // Scripts are not restricted so Livewire/Alpine/Vite keep working.
        $this->assertStringNotContainsString('script-src', $csp);
    }

    public function test_hsts_is_sent_over_https(): void
    {
        $this->get('https://localhost/login')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_api_responses_send_transport_headers_only(): void
    {
        $response = $this->getJson('/api/v1/mobile-config');
        $response->assertOk();

        $response->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeaderMissing('X-Frame-Options')
            ->assertHeaderMissing('Permissions-Policy');
    }

    public function test_error_responses_carry_headers(): void
    {
        $this->get('/definitely-not-a-page')
            ->assertNotFound()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }
}
