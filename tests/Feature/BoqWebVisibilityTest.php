<?php

namespace Tests\Feature;

use App\Livewire\Boqs\Create as BoqsCreate;
use App\Livewire\Boqs\Index as BoqsIndex;
use App\Models\Boq;
use App\Models\Entitlement;
use App\Models\Feature;
use App\Models\Organisation;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The web BOQ pages follow the same project-assignment boundary as BoqPolicy and the API. */
class BoqWebVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $organisation;

    private Boq $boq;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->organisation = Organisation::factory()->create();
        $owner = $this->member('project-manager');
        $project = Project::createForUser($owner, Project::factory()->raw(['user_id' => $owner->id]));
        $this->boq = Boq::factory()->create(['project_id' => $project->id, 'organisation_id' => $this->organisation->id]);
    }

    private function member(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['organisation_id' => $this->organisation->id])->save();
        $user->roles()->attach(Role::where('slug', $role)->value('id'), ['organisation_id' => $this->organisation->id]);

        Entitlement::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $this->organisation->id,
            'feature_id' => Feature::firstOrCreate(['code' => 'boq.management'], ['name' => 'boq.management'])->id,
            'status' => 'active',
            'expires_at' => now()->addMonth(),
        ]);

        return $user->fresh();
    }

    private function listedBoqIds(User $user): array
    {
        return Livewire::actingAs($user)->test(BoqsIndex::class)->viewData('boqs')->pluck('id')->all();
    }

    public function test_unassigned_members_do_not_see_the_organisations_boqs(): void
    {
        $outsider = $this->member('quantity-surveyor');

        $this->assertSame([], $this->listedBoqIds($outsider));
        $this->assertSame(0, Livewire::actingAs($outsider)->test(BoqsIndex::class)->viewData('stats')['total_boqs']);
    }

    public function test_assigned_members_and_administrators_see_them(): void
    {
        $assigned = $this->member('quantity-surveyor');
        $this->boq->project->assignments()->create(['user_id' => $assigned->id, 'role' => 'quantity-surveyor', 'assigned_by' => $assigned->id]);

        $this->assertSame([$this->boq->id], $this->listedBoqIds($assigned));
        $this->assertSame([$this->boq->id], $this->listedBoqIds($this->member('administrator')));
    }

    public function test_unassigned_members_cannot_upload_to_the_project(): void
    {
        $outsider = $this->member('quantity-surveyor');

        Livewire::actingAs($outsider)
            ->test(BoqsCreate::class)
            ->assertViewHas('projects', fn ($projects) => $projects->isEmpty());
    }
}
