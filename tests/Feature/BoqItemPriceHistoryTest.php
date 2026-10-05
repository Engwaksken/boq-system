<?php

namespace Tests\Feature;

use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\BoqItemPriceHistory;
use App\Models\Entitlement;
use App\Models\Feature;
use App\Models\Organisation;
use App\Models\Permission;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoqItemPriceHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function makeItem(): BoqItem
    {
        $org = Organisation::factory()->create();
        $user = User::factory()->create(['organisation_id' => $org->id]);
        $project = Project::factory()->create(['organisation_id' => $org->id, 'user_id' => $user->id]);
        $boq = Boq::factory()->create(['organisation_id' => $org->id, 'project_id' => $project->id]);

        return BoqItem::factory()->create(['boq_id' => $boq->id, 'approved_rate' => 100]);
    }

    private function grantAccess(User $user): void
    {
        $user->permissions()->attach(Permission::factory()->create(['slug' => 'boq.view', 'name' => 'boq.view', 'module' => 'boq']));
        Entitlement::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'feature_id' => Feature::factory()->create(['code' => 'boq.management'])->id,
            'status' => 'active',
            'expires_at' => now()->addDay(),
        ]);
    }

    public function test_rate_change_records_a_price_history_entry(): void
    {
        $item = $this->makeItem();

        $item->update(['approved_rate' => 150]);

        $this->assertDatabaseHas('boq_item_price_histories', [
            'boq_item_id' => $item->id,
            'field' => 'approved_rate',
            'old_value' => '100.00',
            'new_value' => '150.00',
        ]);
    }

    public function test_identical_rate_is_not_recorded(): void
    {
        $item = $this->makeItem();

        $item->update(['approved_rate' => 100]);

        $this->assertSame(0, BoqItemPriceHistory::where('boq_item_id', $item->id)->count());
    }

    public function test_multiple_rate_fields_are_tracked_independently(): void
    {
        $item = $this->makeItem();

        $item->update(['reviewed_rate' => 120, 'approved_rate' => 130]);

        $fields = BoqItemPriceHistory::where('boq_item_id', $item->id)->orderBy('id')->pluck('field')->all();
        $this->assertEquals(['reviewed_rate', 'approved_rate'], $fields);
    }

    public function test_price_history_endpoint_is_authorized_and_scoped(): void
    {
        $org = Organisation::factory()->create();
        $user = User::factory()->create(['organisation_id' => $org->id]);
        $project = Project::factory()->assignedTo($user)->create(['organisation_id' => $org->id, 'user_id' => $user->id]);
        $boq = Boq::factory()->create(['organisation_id' => $org->id, 'project_id' => $project->id]);
        $item = BoqItem::factory()->create(['boq_id' => $boq->id, 'approved_rate' => 100]);
        $item->update(['approved_rate' => 140]);
        $this->grantAccess($user);

        $this->actingAs($user)
            ->getJson('/api/v1/boq-items/'.$item->id.'/price-history')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.field', 'approved_rate');
    }

    public function test_price_history_endpoint_rejects_foreign_tenant(): void
    {
        $item = $this->makeItem();
        $other = User::factory()->create(['organisation_id' => Organisation::factory()->create()->id]);
        $this->grantAccess($other);

        $this->actingAs($other)
            ->getJson('/api/v1/boq-items/'.$item->id.'/price-history')
            ->assertForbidden();
    }
}
