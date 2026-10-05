<?php

namespace Tests\Feature;

use App\Livewire\Boqs\LocationPrices;
use App\Livewire\Boqs\Show;
use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\BoqLocationPrice;
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

class BulkReviewAndLocationPricesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Boq $boq;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->user = User::factory()->create();
        // Admin role: edit and approve BOQs.
        $this->user->roles()->attach(Role::where('slug', 'administrator')->value('id'), ['organisation_id' => $this->user->organisation_id]);
        foreach (['boq.management', 'boq.import.excel'] as $code) {
            Entitlement::factory()->create([
                'user_id' => $this->user->id, 'organisation_id' => $this->user->organisation_id,
                'feature_id' => Feature::firstOrCreate(['code' => $code], ['name' => $code])->id,
                'status' => 'active', 'expires_at' => now()->addMonth(),
            ]);
        }
        $project = Project::factory()->create(['user_id' => $this->user->id, 'organisation_id' => $this->user->organisation_id, 'location' => 'Kampala', 'currency' => 'UGX']);
        $this->boq = Boq::factory()->create(['project_id' => $project->id, 'organisation_id' => $this->user->organisation_id, 'currency' => 'UGX']);
    }

    private function item(array $attributes = []): BoqItem
    {
        return BoqItem::factory()->create($attributes + [
            'boq_id' => $this->boq->id, 'description' => 'Portland Cement 42.5N 50kg bag', 'unit' => 'bag', 'quantity' => 10,
            'currency' => 'UGX', 'ai_suggested_rate' => 36000, 'location' => 'Kampala', 'approved_rate' => null,
            'reviewed_rate' => null, 'status' => 'pending', 'match_type' => null,
        ]);
    }

    public function test_bulk_accept_approve_and_reject_on_the_boq_page(): void
    {
        $a = $this->item();
        $b = $this->item();
        $c = $this->item();
        $noPrice = $this->item(['ai_suggested_rate' => null]);

        $page = Livewire::actingAs($this->user)->test(Show::class, ['boq' => $this->boq]);

        $page->set('selected', [(string) $a->id, (string) $noPrice->id])->call('bulkAcceptSuggested')->assertSet('selected', []);
        $this->assertSame('reviewed', $a->fresh()->status);
        $this->assertEquals(36000, (float) $a->fresh()->reviewed_rate);
        $this->assertSame('pending', $noPrice->fresh()->status);

        $page->set('selected', [(string) $a->id, (string) $b->id])->call('bulkApprove');
        $this->assertSame('approved', $a->fresh()->status);
        $this->assertSame('approved', $b->fresh()->status, 'A suggested price is accepted and approved in one step.');

        $page->set('selected', [(string) $b->id, (string) $c->id])
            ->call('openBulkReject')
            ->assertSet('showBulkReject', true)
            ->call('bulkReject')
            ->assertHasErrors('bulkRejectionReason')
            ->set('bulkRejectionReason', 'Too high for this site')
            ->call('bulkReject');
        $this->assertSame('rejected', $c->fresh()->status);
        $this->assertSame('Too high for this site', $c->fresh()->rejection_reason);
        $this->assertSame('approved', $b->fresh()->status, 'Approved items are kept.');
    }

    public function test_bulk_review_api(): void
    {
        $a = $this->item();
        $b = $this->item();

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/boqs/{$this->boq->id}/items/bulk-review", ['action' => 'accept', 'item_ids' => [$a->id, $b->id]])
            ->assertOk()->assertJsonPath('data.done', 2);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/boqs/{$this->boq->id}/items/bulk-review", ['action' => 'reject', 'item_ids' => [$b->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('reason');
    }

    public function test_prices_are_kept_per_location_compared_and_switched(): void
    {
        $item = $this->item();
        $this->assertSame(1, BoqLocationPrice::count(), 'Pricing an item stores its location price.');

        // The same item priced for Gulu later: both prices are kept.
        $item->update(['ai_suggested_rate' => 40000, 'location' => 'Gulu']);
        $prices = BoqLocationPrice::where('boq_item_id', $item->id)->pluck('rate', 'location_key');
        $this->assertEquals(['kampala' => 36000, 'gulu' => 40000], $prices->map(fn ($r) => (float) $r)->all());

        $api = $this->actingAs($this->user, 'sanctum');
        $locations = $api->getJson("/api/v1/boqs/{$this->boq->id}/locations")->assertOk()->json('data');
        $this->assertEqualsCanonicalizing(['kampala', 'gulu'], array_column($locations, 'key'));
        $this->assertEquals(400000, collect($locations)->firstWhere('key', 'gulu')['total']);

        $api->getJson("/api/v1/boqs/{$this->boq->id}/locations/compare?locations[]=kampala&locations[]=gulu")
            ->assertOk()
            ->assertJsonPath('data.rows.0.lowest', 'kampala');

        // Switch the BOQ back to Kampala's prices.
        $api->postJson("/api/v1/boqs/{$this->boq->id}/locations/use", ['location' => 'Kampala'])->assertOk()->assertJsonPath('data.applied', 1);
        $this->assertEquals(36000, (float) $item->fresh()->ai_suggested_rate);
        $this->assertSame('Kampala', $item->fresh()->location);
    }

    public function test_pricing_for_another_location_keeps_the_first_location(): void
    {
        $item = $this->item(['description' => 'Portland Cement 42.5N 50kg bag']);
        HardwarePrice::create([
            'organisation_id' => null, 'item_name' => 'Portland Cement 42.5N 50kg bag', 'category' => 'Cement', 'unit' => 'bag',
            'price' => 39000, 'currency' => 'UGX', 'supplier' => 'Gulu Hardware', 'location' => 'Gulu', 'is_active' => true, 'fetched_at' => now(),
        ]);

        Livewire::actingAs($this->user)
            ->test(LocationPrices::class, ['boq' => $this->boq])
            ->set('newLocation', 'Gulu')
            ->call('priceForLocation')
            ->assertHasNoErrors()
            ->assertDispatched('boq-prices-updated')
            ->set('compareKeys', ['kampala', 'gulu'])
            ->call('openCompare')
            ->assertSet('showCompare', true)
            ->assertSee('Gulu');

        $this->assertSame('Kampala', $this->boq->project->fresh()->location, 'The project keeps its own location.');
        $this->assertEqualsCanonicalizing(['kampala', 'gulu'], BoqLocationPrice::where('boq_item_id', $item->id)->pluck('location_key')->all());
    }
}
