<?php

namespace Tests\Feature;

use App\Models\Boq;
use App\Models\Entitlement;
use App\Models\Feature;
use App\Models\Organisation;
use App\Models\Permission;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BoqProcessApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_process_a_boq(): void
    {
        $boq = $this->makeBoq();

        $this->postJson(route('api.v1.boqs.process', $boq))
            ->assertStatus(401);
    }

    public function test_process_requires_the_boq_edit_permission(): void
    {
        [$user, $boq] = $this->makeBoqForUser();

        $this->actingAs($user)
            ->postJson(route('api.v1.boqs.process', $boq))
            ->assertStatus(403)
            ->assertJson(['error_code' => 'FORBIDDEN']);
    }

    public function test_process_requires_boq_management_and_import_entitlements(): void
    {
        [$user, $boq] = $this->makeBoqForUser();
        $this->grantPermission($user, 'boq.edit');

        $this->actingAs($user)
            ->postJson(route('api.v1.boqs.process', $boq))
            ->assertStatus(403)
            ->assertJson(['error_code' => 'FEATURE_TOPUP_REQUIRED']);
    }

    public function test_process_imports_the_spreadsheet_and_returns_the_boq_resource_payload(): void
    {
        [$user, $boq] = $this->makeBoqForUser();
        $this->grantPermission($user, 'boq.edit');
        $this->grantEntitlement($user, 'boq.management');
        $this->grantEntitlement($user, 'boq.import.excel', ['boq_imports' => 1]);
        $path = "boqs/{$boq->id}/process-test.csv";
        Storage::disk('local')->put($path, "Item Code,Description,Unit,Quantity,Rate,Amount\nA1,Imported excavation,m3,2,10,27.56\n");
        $boq->update(['source_type' => 'excel', 'source_file_path' => $path]);

        try {
            $this->actingAs($user)
                ->postJson(route('api.v1.boqs.process', $boq))
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('meta.items_imported', 1)
                ->assertJsonPath('data.id', $boq->id)
                ->assertJsonPath('data.status', 'under_review');
        } finally {
            Storage::disk('local')->delete($path);
        }

        $this->assertDatabaseHas('boq_items', [
            'boq_id' => $boq->id,
            'item_code' => 'A1',
            'description' => 'Imported excavation',
            'amount' => 27.56,
        ]);
    }

    public function test_user_cannot_view_a_boq_from_another_tenant(): void
    {
        $boq = $this->makeBoq();
        $otherUser = User::factory()->create(['organisation_id' => Organisation::factory()->create()->id]);
        $this->grantPermission($otherUser, 'boq.view');
        $this->grantEntitlement($otherUser, 'boq.management');

        $this->actingAs($otherUser)
            ->getJson(route('api.v1.boqs.show', $boq))
            ->assertStatus(403);
    }

    public function test_user_can_view_a_boq_in_its_own_tenant(): void
    {
        [$user, $boq] = $this->makeBoqForUser();
        $this->grantPermission($user, 'boq.view');
        $this->grantEntitlement($user, 'boq.management');

        $this->actingAs($user)
            ->getJson(route('api.v1.boqs.show', $boq))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $boq->id);
    }

    /** @return array{User, Boq} */
    private function makeBoqForUser(): array
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create(['organisation_id' => $organisation->id]);
        $project = Project::factory()->create([
            'organisation_id' => $organisation->id,
            'user_id' => $user->id,
        ]);

        return [$user, Boq::factory()->create([
            'organisation_id' => $organisation->id,
            'project_id' => $project->id,
        ])];
    }

    private function makeBoq(): Boq
    {
        [, $boq] = $this->makeBoqForUser();

        return $boq;
    }

    private function grantPermission(User $user, string $slug): void
    {
        $user->permissions()->attach(Permission::factory()->create([
            'name' => $slug,
            'slug' => $slug,
            'module' => 'boq',
        ]));
    }

    private function grantEntitlement(User $user, string $featureCode, array $limits = []): void
    {
        Entitlement::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'feature_id' => Feature::factory()->create(['code' => $featureCode])->id,
            'status' => 'active',
            'expires_at' => now()->addDay(),
            'limits' => $limits ?: null,
        ]);
    }
}
