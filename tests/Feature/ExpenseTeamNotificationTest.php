<?php

namespace Tests\Feature;

use App\Livewire\Expenses\Index as Expenses;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\UserNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExpenseTeamNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function member(?int $organisationId = null): User
    {
        $user = User::factory()->create($organisationId === null ? [] : ['organisation_id' => $organisationId]);
        $user->roles()->attach(Role::where('slug', 'user')->value('id'), ['organisation_id' => $user->organisation_id]);

        return $user;
    }

    public function test_recording_an_expense_notifies_owner_and_assigned_members_but_not_the_recorder(): void
    {
        $recorder = $this->member();
        $organisationId = $recorder->organisation_id;
        $owner = $this->member($organisationId);
        $teammate = $this->member($organisationId);

        $project = Project::factory()->assignedTo($recorder)->create([
            'organisation_id' => $organisationId,
            'user_id' => $owner->id,
            'name' => 'Riverside Block',
        ]);
        $project->assignments()->create(['user_id' => $teammate->id, 'role' => 'site-engineer', 'assigned_by' => $owner->id]);

        Livewire::actingAs($recorder)->test(Expenses::class)
            ->call('create')->set('project_id', $project->id)->set('purchase_date', '2026-10-01')
            ->set('description', 'Cement purchase')->set('quantity', '3')->set('unit', 'bags')
            ->set('rate', '12.5')->set('currency', 'UGX')
            ->call('save')->assertHasNoErrors();

        $notice = UserNotification::where('user_id', $owner->id)->where('type', 'expense')->sole();
        $this->assertStringContainsString($recorder->name, $notice->message);
        $this->assertStringContainsString('Riverside Block', $notice->message);
        $this->assertSame(1, UserNotification::where('user_id', $teammate->id)->where('type', 'expense')->count());
        $this->assertSame(0, UserNotification::where('user_id', $recorder->id)->count());
    }

    public function test_expense_list_shows_who_recorded_each_expense(): void
    {
        $recorder = $this->member();
        $project = Project::factory()->assignedTo($recorder)->create(['organisation_id' => $recorder->organisation_id, 'user_id' => $recorder->id]);

        Livewire::actingAs($recorder)->test(Expenses::class)
            ->call('create')->set('project_id', $project->id)->set('purchase_date', '2026-10-01')
            ->set('description', 'Sand')->set('quantity', '2')->set('unit', 'loads')->set('rate', '40')->set('currency', 'UGX')
            ->call('save')->assertHasNoErrors();

        $this->actingAs($recorder)->get(route('expenses.index'))->assertOk()->assertSee($recorder->name);
    }
}
