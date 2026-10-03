<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\HardwareCategory;
use App\Models\HardwarePrice;
use App\Services\HardwarePriceFetchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DailyPriceScanFilterTest extends TestCase
{
    use RefreshDatabase;

    private function fakeAi(array &$prompts): void
    {
        AiProvider::create([
            'key' => 'deepseek', 'name' => 'DeepSeek', 'provider_type' => 'deepseek',
            'api_base_url' => 'https://api.deepseek.com/v1', 'default_model' => 'deepseek-chat',
            'api_key' => 'secret', 'is_enabled' => true, 'is_default' => true, 'sort_order' => 1,
        ]);

        Http::fake(function ($request) use (&$prompts) {
            $prompts[] = (string) $request->body();

            return Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'item_name' => 'Cement B', 'unit' => 'bag', 'price' => 36000, 'currency' => 'UGX', 'supplier' => 'Hima',
                ])]]],
                'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 5],
            ]);
        });
    }

    public function test_the_daily_scanner_only_refreshes_items_that_already_have_a_price(): void
    {
        // Isolate the scan to one category (migrations seed the canonical ones).
        HardwareCategory::query()->update(['is_active' => false]);
        HardwareCategory::forceCreate([
            'name' => 'Cement', 'slug' => 'cement',
            'default_items' => ['Cement A', 'Cement B', 'Cement C'],
            'is_active' => true, 'sort_order' => 1,
        ]);

        // Only one of the three items has ever been priced.
        HardwarePrice::create([
            'organisation_id' => null,
            'item_name' => 'Cement B',
            'category' => 'Cement',
            'price_type' => HardwarePrice::TYPE_HARDWARE,
            'unit' => 'bag',
            'price' => 35000,
            'currency' => 'UGX',
            'supplier' => 'Hima',
            'location' => 'Kampala',
            'fetched_at' => now(),
            'is_active' => true,
        ]);

        $prompts = [];
        $this->fakeAi($prompts);

        $results = app(HardwarePriceFetchingService::class)
            ->fetchDailyPrices(organisationId: null, location: 'Kampala', limit: 10, onlyPreviouslyPriced: true);

        $this->assertSame(1, $results['fetched']);
        $this->assertCount(1, $prompts);
        $this->assertStringContainsString('Cement B', $prompts[0]);
        $this->assertStringNotContainsString('Cement A', $prompts[0]);
        $this->assertStringNotContainsString('Cement C', $prompts[0]);
        $this->assertSame(0, HardwarePrice::where('item_name', 'Cement A')->count());
        $this->assertSame(0, HardwarePrice::where('item_name', 'Cement C')->count());
    }

    public function test_the_scheduled_command_skips_items_without_a_price(): void
    {
        HardwareCategory::query()->update(['is_active' => false]);
        HardwareCategory::forceCreate([
            'name' => 'Cement', 'slug' => 'cement',
            'default_items' => ['Cement A', 'Cement B'],
            'is_active' => true, 'sort_order' => 1,
        ]);

        HardwarePrice::create([
            'organisation_id' => null,
            'item_name' => 'Cement B',
            'category' => 'Cement',
            'price_type' => HardwarePrice::TYPE_HARDWARE,
            'unit' => 'bag',
            'price' => 35000,
            'currency' => 'UGX',
            'supplier' => 'Hima',
            'location' => 'Kampala',
            'fetched_at' => now(),
            'is_active' => true,
        ]);

        $prompts = [];
        $this->fakeAi($prompts);

        $this->artisan('hardware:fetch-daily')->assertSuccessful();

        $this->assertCount(1, $prompts);
        $this->assertStringContainsString('Cement B', $prompts[0]);
        $this->assertStringNotContainsString('Cement A', $prompts[0]);
    }

    public function test_the_manual_category_scan_can_still_discover_unpriced_items(): void
    {
        HardwareCategory::query()->update(['is_active' => false]);
        HardwareCategory::forceCreate([
            'name' => 'Cement', 'slug' => 'cement',
            'default_items' => ['Cement A'],
            'is_active' => true, 'sort_order' => 1,
        ]);

        $prompts = [];
        $this->fakeAi($prompts);

        // The manual path does not pass onlyPreviouslyPriced.
        $results = app(HardwarePriceFetchingService::class)
            ->fetchPricesForCategory('Cement', 'Kampala', 10, null, HardwarePrice::TYPE_HARDWARE);

        $this->assertCount(1, $results);
        $this->assertCount(1, $prompts);
        $this->assertStringContainsString('Cement A', $prompts[0]);
    }
}
