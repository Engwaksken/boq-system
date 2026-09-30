<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\HardwareCategory;
use App\Models\HardwarePrice;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HardwarePriceFetchNowTest extends TestCase
{
    use RefreshDatabase;

    /** A manager with the price management permission (not a super admin). */
    private function priceManager(): User
    {
        $role = Role::firstOrCreate(['slug' => 'manager'], ['name' => 'Manager']);
        foreach (['hardware-prices.view', 'hardware-prices.manage'] as $slug) {
            $role->permissions()->syncWithoutDetaching([Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'module' => 'rates'])->id]);
        }
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function fakeAi(): void
    {
        AiProvider::create([
            'key' => 'deepseek', 'name' => 'DeepSeek', 'provider_type' => 'deepseek',
            'api_base_url' => 'https://api.deepseek.com/v1', 'default_model' => 'deepseek-chat',
            'api_key' => 'secret', 'is_enabled' => true, 'is_default' => true, 'sort_order' => 1,
        ]);
        Http::fake(['api.deepseek.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode(['item_name' => 'Portland cement 50kg', 'unit' => 'bag', 'price' => 36000, 'currency' => 'UGX', 'supplier' => 'Hima'])]]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10],
        ])]);
    }

    public function test_fetch_latest_prices_answers_at_once_and_fetches_in_the_background(): void
    {
        HardwareCategory::forceCreate(['name' => 'Cement', 'slug' => 'cement', 'default_items' => ['Portland cement 50kg'], 'is_active' => true, 'sort_order' => 1]);
        $this->fakeAi();
        $manager = $this->priceManager();

        $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/hardware-prices/fetch')
            ->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'started')
            ->assertJsonMissingPath('exception');

        // The fetch ran after the response and released its lock.
        $this->assertGreaterThan(0, HardwarePrice::where('item_name', 'Portland cement 50kg')->count());
        $this->actingAs($manager, 'sanctum')->getJson('/api/v1/hardware-prices/fetch-status')
            ->assertOk()
            ->assertJsonPath('data.running', false)
            ->assertJsonPath('data.last.fetched', fn ($fetched) => $fetched > 0);
    }

    public function test_a_second_fetch_while_one_is_running_is_not_started(): void
    {
        $manager = $this->priceManager();
        Cache::put('hardware-price-fetch-running-'.($manager->organisation_id ?? 'general'), now()->toIso8601String(), 600);

        $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/hardware-prices/fetch')
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'running');

        $this->actingAs($manager, 'sanctum')->getJson('/api/v1/hardware-prices/fetch-status')
            ->assertJsonPath('data.running', true);
    }
}
