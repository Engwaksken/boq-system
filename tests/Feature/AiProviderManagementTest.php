<?php

namespace Tests\Feature;

use App\Livewire\Admin\AiProviders;
use App\Models\AiProvider;
use App\Models\Role;
use App\Models\User;
use App\Services\AiProviderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AiProviderManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function member(string $role = 'administrator'): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', $role)->value('id'), ['organisation_id' => $user->organisation_id]);

        return $user;
    }

    public function test_admin_presets_are_idempotent_disabled_and_tenant_scoped(): void
    {
        $admin = $this->member();
        $component = Livewire::actingAs($admin)->test(AiProviders::class)->call('addProviderPresets');
        $this->assertSame(18, AiProvider::count());
        $this->assertSame(0, AiProvider::where('is_enabled', true)->count());
        $this->assertSame(18, AiProvider::where('organisation_id', $admin->organisation_id)->count());
        $component->call('addProviderPresets');
        $this->assertSame(18, AiProvider::count());
        $foreign = AiProvider::create(['key' => 'foreign', 'name' => 'Foreign secret', 'provider_type' => 'openai', 'api_key' => 'foreign-secret']);
        $component->assertDontSee('Foreign secret');
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $component->call('edit', $foreign->id);
    }

    public function test_admin_can_save_test_enable_and_set_organisation_default_without_exposing_secret(): void
    {
        $admin = $this->member();
        Http::fake(['*' => Http::response(['content' => [['type' => 'text', 'text' => '{"ok":true}']]])]);
        $component = Livewire::actingAs($admin)->test(AiProviders::class)->call('create')
            ->set('form.provider_type', 'anthropic')->assertSet('form.api_base_url', 'https://api.anthropic.com')
            ->set('form.api_key', 'claude-secret')->set('form.organisation_id', null)->call('save')->assertHasNoErrors();
        $provider = AiProvider::firstOrFail();
        $this->assertSame($admin->organisation_id, $provider->organisation_id);
        $component->call('testConnection', $provider->id)->assertSet('testResult.ok', true)
            ->call('closeTestModal')->call('setDefault', $provider->id)->call('edit', $provider->id)
            ->assertSet('form.api_key', '***stored***')->assertDontSee('claude-secret');
        $this->assertTrue($provider->fresh()->is_default);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.anthropic.com/v1/messages'
            && $request->hasHeader('x-api-key', 'claude-secret') && $request['max_tokens'] === 4096);
        Livewire::actingAs($this->member('user'))->test(AiProviders::class)->assertForbidden();
    }

    public function test_azure_uses_deployment_api_key_and_version_and_rejects_invalid_test_response(): void
    {
        $provider = AiProvider::create(['key' => 'azure', 'name' => 'Azure', 'provider_type' => 'azure_openai',
            'api_base_url' => 'https://example.openai.azure.com', 'default_model' => 'my-deployment', 'api_key' => 'azure-secret']);
        Http::fake(['*' => Http::sequence()->push(['choices' => [['message' => ['content' => '{"ok":true}']]]])
            ->push(['choices' => [['message' => ['content' => '{"ok":false}']]]])]);
        $this->assertTrue(app(AiProviderService::class)->test($provider)['ok']);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/openai/deployments/my-deployment/chat/completions?api-version=2024-10-21')
            && $request->hasHeader('api-key', 'azure-secret'));
        $this->assertFalse(app(AiProviderService::class)->test($provider)['ok']);
        $this->assertSame('failed', $provider->fresh()->last_test_status);
    }

    public function test_compatible_presets_use_their_expected_endpoint_and_authentication(): void
    {
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '{"ok":true}']]]])]);
        foreach (config('ai-providers') as $type => $preset) {
            if (in_array($type, ['gemini', 'anthropic', 'azure_openai', 'custom', 'openai_compatible'], true)) {
                continue;
            }
            $provider = AiProvider::create(['key' => $type, 'name' => $preset['name'], 'provider_type' => $type,
                'api_base_url' => $preset['url'], 'default_model' => $preset['model'] ?: 'test-model', 'api_key' => 'test-secret']);
            $this->assertTrue(app(AiProviderService::class)->test($provider)['ok'], $type);
            $endpoint = $preset['url'].($preset['endpoint'] ?? '/chat/completions');
            Http::assertSent(fn (Request $request) => $request->url() === $endpoint && $request->hasHeader('Authorization', 'Bearer test-secret'));
        }
    }
}
