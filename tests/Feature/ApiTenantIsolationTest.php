<?php

namespace Tests\Feature;

use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\Entitlement;
use App\Models\Feature;
use App\Models\HardwarePrice;
use App\Models\Organisation;
use App\Models\Project;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(string $slug, ?int $organisationId): User
    {
        $user = User::factory()->create();
        $user->forceFill(['organisation_id' => $organisationId])->save();
        $user->roles()->attach(Role::where('slug', $slug)->value('id'), ['organisation_id' => $organisationId]);

        Entitlement::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $organisationId,
            'feature_id' => Feature::firstOrCreate(['code' => 'boq.management'], ['name' => 'boq.management'])->id,
            'status' => 'active',
            'expires_at' => now()->addMonth(),
        ]);

        return $user->fresh();
    }

    private function personalBoq(User $user): Boq
    {
        $project = Project::factory()->create(['user_id' => $user->id, 'organisation_id' => null]);

        return Boq::factory()->create(['project_id' => $project->id, 'organisation_id' => null]);
    }

    public function test_users_without_an_organisation_only_list_their_own_boqs(): void
    {
        $alice = $this->userWithRole('user', null);
        $bob = $this->userWithRole('user', null);
        $mine = $this->personalBoq($alice);
        $theirs = $this->personalBoq($bob);

        $ids = collect($this->actingAs($alice, 'sanctum')->getJson('/api/v1/boqs')->assertOk()->json('data.data'))->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));
        $this->getJson("/api/v1/boqs/{$theirs->id}")->assertForbidden();
    }

    public function test_beneficiary_search_is_limited_to_administrators(): void
    {
        $customer = $this->userWithRole('user', null);
        User::factory()->create(['name' => 'Searchable Person']);

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/v1/subscriptions/proxy/beneficiaries?query=Searchable')
            ->assertForbidden();
    }

    public function test_shared_suppliers_can_only_be_changed_by_their_creator_organisation(): void
    {
        $orgA = Organisation::factory()->create();
        $orgB = Organisation::factory()->create();
        $creator = $this->userWithRole('procurement-officer', $orgA->id);
        $outsider = $this->userWithRole('procurement-officer', $orgB->id);
        $supplier = Supplier::factory()->create(['created_by' => $creator->id, 'name' => 'Original']);

        $this->actingAs($outsider, 'sanctum')
            ->putJson("/api/v1/suppliers/{$supplier->id}", ['name' => 'Hijacked'])
            ->assertForbidden();
        $this->deleteJson("/api/v1/suppliers/{$supplier->id}")->assertForbidden();
        $this->assertSame('Original', $supplier->fresh()->name);

        $this->actingAs($creator, 'sanctum')
            ->putJson("/api/v1/suppliers/{$supplier->id}", ['name' => 'Renamed'])
            ->assertOk();
        $this->assertSame('Renamed', $supplier->fresh()->name);
    }

    public function test_another_organisations_private_price_cannot_be_applied(): void
    {
        $orgA = Organisation::factory()->create();
        $orgB = Organisation::factory()->create();
        $user = $this->userWithRole('administrator', $orgA->id);
        $project = Project::factory()->create(['user_id' => $user->id, 'organisation_id' => $orgA->id]);
        $boq = Boq::factory()->create(['project_id' => $project->id, 'organisation_id' => $orgA->id]);
        $item = BoqItem::factory()->create(['boq_id' => $boq->id]);
        $private = HardwarePrice::factory()->create(['organisation_id' => $orgB->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/boq-items/{$item->id}/apply-price", ['hardware_price_id' => $private->id])
            ->assertForbidden();
    }

    public function test_hardware_price_statistics_load(): void
    {
        $user = $this->userWithRole('user', null);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/hardware-prices/statistics')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_quotations_cannot_be_attached_to_another_tenants_project(): void
    {
        $orgA = Organisation::factory()->create();
        $orgB = Organisation::factory()->create();
        $user = $this->userWithRole('procurement-officer', $orgA->id);
        $foreignProject = Project::factory()->create(['organisation_id' => $orgB->id]);
        $supplier = Supplier::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/quotations', [
                'supplier_id' => $supplier->id,
                'project_id' => $foreignProject->id,
                'items' => [['product' => 'Cement', 'unit_price' => 10]],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('quotations', ['project_id' => $foreignProject->id]);
    }
}
