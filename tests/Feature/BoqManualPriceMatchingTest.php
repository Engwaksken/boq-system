<?php

namespace Tests\Feature;

use App\Livewire\Boqs\Show;
use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\HardwarePrice;
use App\Models\Organisation;
use App\Models\Permission;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BoqManualPriceMatchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_matching_panel_shows_automatic_candidates_and_location_prioritised_results(): void
    {
        [$organisation, $user, $boq] = $this->makeBoq();
        $this->grantEdit($user);
        $item = $this->makeItem($boq, ['description' => 'Portland cement 50kg bag', 'unit' => 'bag']);
        $alternate = $this->makePrice($organisation, ['item_name' => 'Portland cement 50kg bag', 'location' => 'Gulu']);
        $local = $this->makePrice($organisation, ['item_name' => 'Portland cement 50kg bag', 'location' => 'Kampala']);

        $component = Livewire::actingAs($user)->test(Show::class, ['boq' => $boq])
            ->call('startMatching', $item->id)
            ->assertSet('matchingItemId', $item->id);

        $this->assertSame($local->id, $component->get('automaticCandidates')[0]['id']);
        $this->assertSame($local->id, $component->get('matchResults')[0]['id']);
        $this->assertContains($alternate->id, collect($component->get('matchResults'))->pluck('id'));
    }

    public function test_free_search_covers_brand_specification_category_and_supplier(): void
    {
        [$organisation, $user, $boq] = $this->makeBoq();
        $this->grantEdit($user);
        $item = $this->makeItem($boq);
        $price = $this->makePrice($organisation, [
            'brand' => 'Acme',
            'specification' => 'Marine grade',
            'category' => 'Fasteners',
            'supplier' => 'Builders Hub',
        ]);

        foreach (['Acme', 'Marine grade', 'Fasteners', 'Builders Hub'] as $search) {
            $component = Livewire::actingAs($user)->test(Show::class, ['boq' => $boq])
                ->call('startMatching', $item->id)
                ->set('matchSearch', $search);

            $this->assertSame([$price->id], collect($component->get('matchResults'))->pluck('id')->all());
        }
    }

    public function test_search_only_returns_active_same_tenant_same_currency_prices(): void
    {
        [$organisation, $user, $boq] = $this->makeBoq();
        $this->grantEdit($user);
        $item = $this->makeItem($boq);
        $valid = $this->makePrice($organisation);
        $this->makePrice($organisation, ['currency' => 'USD']);
        $this->makePrice($organisation, ['is_active' => false]);
        $this->makePrice(Organisation::factory()->create());

        $component = Livewire::actingAs($user)->test(Show::class, ['boq' => $boq])
            ->call('startMatching', $item->id);

        $this->assertSame([$valid->id], collect($component->get('matchResults'))->pluck('id')->all());
    }

    public function test_manual_selection_persists_provenance_but_does_not_review_or_approve(): void
    {
        [$organisation, $user, $boq] = $this->makeBoq();
        $this->grantEdit($user);
        $item = $this->makeItem($boq, [
            'reviewed_rate' => 90,
            'approved_rate' => 90,
            'status' => 'reviewed',
        ]);
        $price = $this->makePrice($organisation, ['price' => 125, 'location' => 'Jinja']);

        Livewire::actingAs($user)->test(Show::class, ['boq' => $boq])
            ->call('startMatching', $item->id)
            ->call('selectHardwarePrice', $item->id, $price->id)
            ->assertSet('matchingItemId', null)
            ->assertHasNoErrors();

        $item->refresh();
        $this->assertSame($price->id, $item->hardware_price_id);
        $this->assertSame('manual', $item->match_type);
        $this->assertSame($user->id, $item->matched_by);
        $this->assertNotNull($item->matched_at);
        $this->assertSame('125.00', $item->ai_suggested_rate);
        $this->assertSame('hardware_price:'.$price->id, $item->pricing_source);
        $this->assertSame('Jinja', $item->location);
        $this->assertNull($item->reviewed_rate);
        $this->assertNull($item->approved_rate);
        $this->assertSame('pending', $item->status);
    }

    public function test_manual_selection_rejects_a_cross_tenant_price(): void
    {
        [, $user, $boq] = $this->makeBoq();
        $this->grantEdit($user);
        $item = $this->makeItem($boq);
        $price = $this->makePrice(Organisation::factory()->create());

        Livewire::actingAs($user)->test(Show::class, ['boq' => $boq])
            ->call('selectHardwarePrice', $item->id, $price->id)
            ->assertForbidden();

        $this->assertNull($item->fresh()->hardware_price_id);
    }

    public function test_manual_selection_cannot_target_an_item_from_another_boq(): void
    {
        [$organisation, $user, $boq] = $this->makeBoq();
        $this->grantEdit($user);
        [, , $otherBoq] = $this->makeBoq();
        $otherItem = $this->makeItem($otherBoq);
        $price = $this->makePrice($organisation);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($user)->test(Show::class, ['boq' => $boq])
            ->call('selectHardwarePrice', $otherItem->id, $price->id);
    }

    public function test_matching_actions_require_boq_edit_permission(): void
    {
        [$organisation, $user, $boq] = $this->makeBoq();
        $item = $this->makeItem($boq);
        $price = $this->makePrice($organisation);

        Livewire::actingAs($user)->test(Show::class, ['boq' => $boq])
            ->call('startMatching', $item->id)
            ->assertForbidden();

        Livewire::actingAs($user)->test(Show::class, ['boq' => $boq])
            ->call('selectHardwarePrice', $item->id, $price->id)
            ->assertForbidden();
    }

    /** @return array{Organisation, User, Boq} */
    private function makeBoq(): array
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create(['organisation_id' => $organisation->id]);
        $project = Project::factory()->create([
            'organisation_id' => $organisation->id,
            'user_id' => $user->id,
            'location' => 'Kampala',
            'currency' => 'UGX',
        ]);
        $boq = Boq::factory()->create([
            'organisation_id' => $organisation->id,
            'project_id' => $project->id,
            'currency' => 'UGX',
            'status' => 'under_review',
        ]);

        return [$organisation, $user, $boq];
    }

    private function makeItem(Boq $boq, array $attributes = []): BoqItem
    {
        return BoqItem::factory()->create(array_merge([
            'boq_id' => $boq->id,
            'description' => 'Steel fixing item',
            'unit' => 'piece',
            'currency' => 'UGX',
            'ai_suggested_rate' => null,
            'reviewed_rate' => null,
            'approved_rate' => null,
            'status' => 'pending',
        ], $attributes));
    }

    private function makePrice(Organisation $organisation, array $attributes = []): HardwarePrice
    {
        return HardwarePrice::create(array_merge([
            'organisation_id' => $organisation->id,
            'item_name' => 'Steel fixing item',
            'category' => 'Steel',
            'unit' => 'piece',
            'price' => 100,
            'currency' => 'UGX',
            'supplier' => 'Supplier',
            'location' => 'Kampala',
            'fetched_at' => now(),
            'is_active' => true,
        ], $attributes));
    }

    private function grantEdit(User $user): void
    {
        $permission = Permission::factory()->create([
            'name' => 'boq.edit',
            'slug' => 'boq.edit',
            'module' => 'boq',
        ]);
        $user->permissions()->attach($permission);
    }
}
