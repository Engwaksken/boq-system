<?php

namespace Tests\Feature;

use App\Livewire\Boqs\Index as BoqsIndex;
use App\Livewire\Boqs\Show as BoqsShow;
use App\Models\Boq;
use App\Models\Entitlement;
use App\Models\Feature;
use App\Models\Organisation;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BoqManagementTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(\App\Models\Role::where('slug', 'user')->value('id'));

        Entitlement::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'feature_id' => Feature::firstOrCreate(['code' => 'boq.management'], ['name' => 'BOQ management'])->id,
            'status' => 'active',
            'expires_at' => now()->addMonth(),
        ]);

        return $user;
    }

    private function boqFor(User $user, string $name = 'Original'): Boq
    {
        $project = Project::factory()->create(['user_id' => $user->id, 'organisation_id' => $user->organisation_id]);

        return Boq::factory()->create(['project_id' => $project->id, 'organisation_id' => $user->organisation_id, 'name' => $name]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_owner_can_rename_and_move_a_boq(): void
    {
        $user = $this->customer();
        $boq = $this->boqFor($user);
        $other = Project::factory()->create(['user_id' => $user->id, 'organisation_id' => $user->organisation_id, 'name' => 'Second']);

        Livewire::actingAs($user)
            ->test(BoqsIndex::class)
            ->call('editBoq', $boq->id)
            ->assertSet('editName', 'Original')
            ->set('editName', 'Renamed BOQ')
            ->set('editProjectId', $other->id)
            ->call('saveBoq')
            ->assertHasNoErrors();

        $boq->refresh();
        $this->assertSame('Renamed BOQ', $boq->name);
        $this->assertSame($other->id, $boq->project_id);
    }

    public function test_boq_cannot_be_moved_into_someone_elses_project(): void
    {
        $user = $this->customer();
        $boq = $this->boqFor($user);
        $foreign = Project::factory()->create(['organisation_id' => Organisation::factory()->create()->id]);

        Livewire::actingAs($user)
            ->test(BoqsIndex::class)
            ->call('editBoq', $boq->id)
            ->set('editProjectId', $foreign->id)
            ->call('saveBoq')
            ->assertHasErrors('editProjectId');

        $this->assertNotSame($foreign->id, $boq->fresh()->project_id);
    }

    public function test_owner_can_delete_single_and_bulk(): void
    {
        $user = $this->customer();
        $one = $this->boqFor($user, 'One');
        $two = $this->boqFor($user, 'Two');
        $three = $this->boqFor($user, 'Three');

        Livewire::actingAs($user)
            ->test(BoqsIndex::class)
            ->call('confirmDelete', $one->id)
            ->call('deleteBoq');

        $this->assertSoftDeleted('boqs', ['id' => $one->id]);

        Livewire::actingAs($user)
            ->test(BoqsIndex::class)
            ->set('selected', [(string) $two->id, (string) $three->id])
            ->call('bulkDelete');

        $this->assertSoftDeleted('boqs', ['id' => $two->id]);
        $this->assertSoftDeleted('boqs', ['id' => $three->id]);
    }

    public function test_owner_can_delete_from_the_boq_page(): void
    {
        $user = $this->customer();
        $boq = $this->boqFor($user);

        Livewire::actingAs($user)
            ->test(BoqsShow::class, ['boq' => $boq])
            ->call('deleteBoq')
            ->assertRedirect(route('boqs.index'));

        $this->assertSoftDeleted('boqs', ['id' => $boq->id]);
    }

    public function test_other_customers_cannot_edit_or_delete(): void
    {
        $owner = $this->customer();
        $boq = $this->boqFor($owner);
        $intruder = $this->customer();

        Livewire::actingAs($intruder)->test(BoqsIndex::class)->call('editBoq', $boq->id)->assertForbidden();
        Livewire::actingAs($intruder)->test(BoqsIndex::class)->call('confirmDelete', $boq->id)->assertForbidden();

        Livewire::actingAs($intruder)
            ->test(BoqsIndex::class)
            ->set('selected', [(string) $boq->id])
            ->call('bulkDelete');

        $this->assertNotSoftDeleted('boqs', ['id' => $boq->id]);
    }
}
