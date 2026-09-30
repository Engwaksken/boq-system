<?php

namespace Tests\Feature;

use App\Jobs\ProcessBoqJob;
use App\Livewire\Boqs\Show;
use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\BoqPricingBatch;
use App\Models\Entitlement;
use App\Models\Feature;
use App\Models\HardwarePrice;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ChunkedBoqPricingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Boq $boq;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->user = User::factory()->create();
        $this->user->roles()->attach(Role::where('slug', 'user')->value('id'));
        foreach (['boq.management', 'boq.import.excel'] as $code) {
            Entitlement::factory()->create([
                'user_id' => $this->user->id, 'organisation_id' => $this->user->organisation_id,
                'feature_id' => Feature::firstOrCreate(['code' => $code], ['name' => $code])->id,
                'status' => 'active', 'expires_at' => now()->addMonth(),
            ]);
        }
        $project = Project::factory()->create(['user_id' => $this->user->id, 'organisation_id' => $this->user->organisation_id, 'location' => 'Kampala', 'currency' => 'UGX']);
        $this->boq = Boq::factory()->create(['project_id' => $project->id, 'organisation_id' => $this->user->organisation_id, 'currency' => 'UGX']);
        HardwarePrice::create([
            'organisation_id' => null, 'item_name' => 'Portland Cement 42.5N 50kg bag', 'category' => 'Cement',
            'unit' => 'bag', 'price' => 36000, 'currency' => 'UGX', 'supplier' => 'Hima', 'location' => 'Kampala',
            'is_active' => true, 'fetched_at' => now(),
        ]);
    }

    private function items(int $count, array $attributes = []): array
    {
        return collect(range(1, $count))->map(fn () => BoqItem::factory()->create($attributes + [
            'boq_id' => $this->boq->id, 'description' => 'Portland Cement 42.5N 50kg bag', 'unit' => 'bag', 'currency' => 'UGX',
            'ai_suggested_rate' => null, 'approved_rate' => null, 'match_type' => null, 'status' => 'pending',
        ]))->all();
    }

    private function batch(array $attributes = []): BoqPricingBatch
    {
        return BoqPricingBatch::create($attributes + [
            'boq_id' => $this->boq->id, 'organisation_id' => $this->user->organisation_id, 'user_id' => $this->user->id,
            'location' => 'Kampala', 'operation' => 'generation', 'status' => 'queued', 'current_stage' => 'queued',
            'total_items' => $this->boq->items()->count(), 'processed_items' => 0, 'failed_items' => 0,
        ]);
    }

    public function test_a_run_that_runs_out_of_time_continues_where_it_stopped(): void
    {
        $items = $this->items(4);
        $batch = $this->batch();

        // No time at all: each run prices one item, the next run continues.
        ProcessBoqJob::dispatchSync($batch->id, 0);

        $this->assertSame('completed', $batch->fresh()->status);
        foreach ($items as $item) {
            $this->assertEquals(36000, (float) $item->fresh()->ai_suggested_rate);
        }
    }

    public function test_the_page_continues_a_batch_that_stopped_part_way(): void
    {
        [$first, $second, $third] = $this->items(3);
        // The first chunk already priced the first item (at an older price).
        $first->forceFill(['ai_suggested_rate' => 35000, 'pricing_status' => 'priced', 'pricing_source' => 'manual', 'match_type' => 'manual'])->save();
        $batch = $this->batch(['status' => 'running', 'last_item_id' => $first->id]);
        BoqPricingBatch::whereKey($batch->id)->update(['updated_at' => now()->subMinute()]);

        Livewire::actingAs($this->user)->test(Show::class, ['boq' => $this->boq])->call('refreshProcessingStatus');

        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertEquals(35000, (float) $first->fresh()->ai_suggested_rate, 'Items before the saved position are not priced again.');
        $this->assertEquals(36000, (float) $third->fresh()->ai_suggested_rate);
    }

    public function test_admins_get_prices_for_selected_items_only(): void
    {
        [$chosen, $alsoChosen, $notChosen] = $this->items(3);
        $approved = $this->items(1, ['status' => 'approved', 'approved_rate' => 1000])[0];

        Livewire::actingAs($this->user)
            ->test(Show::class, ['boq' => $this->boq])
            ->set('selected', [(string) $chosen->id, (string) $alsoChosen->id, (string) $approved->id])
            ->call('priceSelected')
            ->assertSet('selected', []);

        $this->assertEquals(36000, (float) $chosen->fresh()->ai_suggested_rate);
        $this->assertEquals(36000, (float) $alsoChosen->fresh()->ai_suggested_rate);
        $this->assertNull($notChosen->fresh()->ai_suggested_rate);
        $this->assertEquals(1000, (float) $approved->fresh()->approved_rate);

        $batch = BoqPricingBatch::where('operation', 'selection')->firstOrFail();
        $this->assertSame([$chosen->id, $alsoChosen->id], $batch->item_ids);
        $this->assertSame(2, $batch->total_items);
    }
    public function test_items_without_a_suggested_price_can_be_filtered_and_scanned(): void
    {
        [$unpriced, $failed] = $this->items(2);
        $failed->forceFill(['pricing_status' => 'failed', 'pricing_error' => 'No market price was found.'])->save();
        $priced = $this->items(1, ['ai_suggested_rate' => 30000, 'description' => 'Already priced sand'])[0];
        $approved = $this->items(1, ['status' => 'approved', 'approved_rate' => 1000, 'description' => 'Approved steel'])[0];

        $page = Livewire::actingAs($this->user)
            ->test(Show::class, ['boq' => $this->boq])
            ->assertSee('No price yet')
            ->assertSee('No price found')
            ->assertSee('No market price was found.')
            ->assertSee('Scan all 2 unpriced items')
            ->set('itemStatus', 'unpriced')
            ->assertDontSee('Already priced sand')
            ->assertDontSee('Approved steel');
        $this->assertSame(2, $page->viewData('itemStats')['unpriced']);
        $this->assertSame(2, $page->viewData('items')->total());

        // One item.
        $page->call('scanItem', $unpriced->id);
        $this->assertEquals(36000, (float) $unpriced->fresh()->ai_suggested_rate);
        $this->assertNull($failed->fresh()->ai_suggested_rate);

        // Every remaining unpriced item; priced and approved ones are left alone.
        Livewire::actingAs($this->user)->test(Show::class, ['boq' => $this->boq])->call('scanUnpriced');
        $this->assertEquals(36000, (float) $failed->fresh()->ai_suggested_rate);
        $this->assertEquals(30000, (float) $priced->fresh()->ai_suggested_rate);
        $this->assertNull($approved->fresh()->ai_suggested_rate);

        Livewire::actingAs($this->user)->test(Show::class, ['boq' => $this->boq])
            ->assertDontSee('unpriced items')
            ->call('scanUnpriced')
            ->assertSee('Every item already has a suggested price.');
    }

    public function test_approved_items_are_not_scanned_one_by_one(): void
    {
        $approved = $this->items(1, ['status' => 'approved', 'approved_rate' => 1000])[0];

        Livewire::actingAs($this->user)->test(Show::class, ['boq' => $this->boq])
            ->call('scanItem', $approved->id)
            ->assertSee('Approved items keep their price.');

        $this->assertNull($approved->fresh()->ai_suggested_rate);
    }
}
