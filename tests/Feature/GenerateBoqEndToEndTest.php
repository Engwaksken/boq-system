<?php

namespace Tests\Feature;

use App\Livewire\Boqs\Show;
use App\Models\AiProvider;
use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\Entitlement;
use App\Models\Feature;
use App\Models\HardwarePrice;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class GenerateBoqEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_boq_prices_items_from_market_prices_and_ai(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', 'user')->value('id'));
        foreach (['boq.management', 'boq.import.excel'] as $code) {
            Entitlement::factory()->create([
                'user_id' => $user->id, 'organisation_id' => $user->organisation_id,
                'feature_id' => Feature::firstOrCreate(['code' => $code], ['name' => $code])->id,
                'status' => 'active', 'expires_at' => now()->addMonth(),
            ]);
        }
        AiProvider::create([
            'key' => 'deepseek', 'name' => 'DeepSeek', 'provider_type' => 'deepseek',
            'api_base_url' => 'https://api.deepseek.com/v1', 'default_model' => 'deepseek-chat',
            'api_key' => 'secret', 'is_enabled' => true, 'is_default' => true, 'sort_order' => 1,
        ]);
        Http::fake(['api.deepseek.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'suggested_rate' => 1500, 'rate' => 1500, 'unit_rate' => 1500, 'price' => 1500,
                'currency' => 'UGX', 'confidence' => 0.8, 'source' => 'estimate', 'notes' => 'test',
            ])]]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10],
        ])]);

        $project = Project::factory()->create(['user_id' => $user->id, 'organisation_id' => $user->organisation_id, 'location' => null, 'district' => null, 'country' => null, 'currency' => 'UGX']);
        $boq = Boq::factory()->create(['project_id' => $project->id, 'organisation_id' => $user->organisation_id, 'currency' => 'UGX']);
        $cement = BoqItem::factory()->create(['boq_id' => $boq->id, 'description' => 'Portland Cement 42.5N 50kg bag', 'unit' => 'bag', 'quantity' => 10, 'currency' => 'UGX', 'original_rate' => null, 'ai_suggested_rate' => null, 'approved_rate' => null, 'match_type' => null]);
        $labour = BoqItem::factory()->create(['boq_id' => $boq->id, 'description' => 'Excavate trench for foundations', 'unit' => 'm3', 'quantity' => 5, 'currency' => 'UGX', 'original_rate' => null, 'ai_suggested_rate' => null, 'approved_rate' => null, 'match_type' => null]);
        HardwarePrice::create([
            'organisation_id' => null, 'item_name' => 'Portland Cement 42.5N 50kg bag', 'category' => 'Cement',
            'unit' => 'bag', 'price' => 36000, 'currency' => 'UGX', 'supplier' => 'Hima', 'location' => 'Kampala',
            'is_active' => true, 'fetched_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(Show::class, ['boq' => $boq])
            ->call('openGenerate')
            ->set('projectLocation', 'Kampala')
            ->call('generateBoq')
            ->assertHasNoErrors();


        $this->assertEquals(36000, (float) $cement->fresh()->ai_suggested_rate);
        $this->assertNotNull($labour->fresh()->ai_suggested_rate);
    }

    public function test_the_boq_page_runs_generation_itself_when_no_queue_worker_picks_it_up(): void
    {
        config(['queue.default' => 'database']);
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', 'user')->value('id'));
        foreach (['boq.management', 'boq.import.excel'] as $code) {
            Entitlement::factory()->create([
                'user_id' => $user->id, 'organisation_id' => $user->organisation_id,
                'feature_id' => Feature::firstOrCreate(['code' => $code], ['name' => $code])->id,
                'status' => 'active', 'expires_at' => now()->addMonth(),
            ]);
        }
        $project = Project::factory()->create(['user_id' => $user->id, 'organisation_id' => $user->organisation_id, 'location' => 'Kampala', 'currency' => 'UGX']);
        $boq = Boq::factory()->create(['project_id' => $project->id, 'organisation_id' => $user->organisation_id, 'currency' => 'UGX']);
        $item = BoqItem::factory()->create(['boq_id' => $boq->id, 'description' => 'Portland Cement 42.5N 50kg bag', 'unit' => 'bag', 'currency' => 'UGX', 'ai_suggested_rate' => null, 'approved_rate' => null, 'match_type' => null]);
        HardwarePrice::create([
            'organisation_id' => null, 'item_name' => 'Portland Cement 42.5N 50kg bag', 'category' => 'Cement',
            'unit' => 'bag', 'price' => 36000, 'currency' => 'UGX', 'supplier' => 'Hima', 'location' => 'Kampala',
            'is_active' => true, 'fetched_at' => now(),
        ]);

        $page = Livewire::actingAs($user)
            ->test(Show::class, ['boq' => $boq])
            ->call('openGenerate')
            ->call('generateBoq');

        $batch = \App\Models\BoqPricingBatch::where('boq_id', $boq->id)->latest()->firstOrFail();
        $this->assertSame('queued', $batch->status);

        // Nothing picks the job up; after a short wait the page's poll runs it.
        $this->travel(1)->minutes();
        $page->call('refreshProcessingStatus');

        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertEquals(36000, (float) $item->fresh()->ai_suggested_rate);
    }
}
