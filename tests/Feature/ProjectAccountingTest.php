<?php

namespace Tests\Feature;

use App\Livewire\Reports\Accounting;
use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\Expense;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\ProjectAccountingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectAccountingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function member(string $role = 'user'): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', $role)->value('id'), ['organisation_id' => $user->organisation_id]);

        return $user;
    }

    private function project(User $user): Project
    {
        return Project::factory()->assignedTo($user)->create(['organisation_id' => $user->organisation_id, 'currency' => 'UGX']);
    }

    public function test_breakdown_tracks_budget_expenditure_and_balance(): void
    {
        $user = $this->member();
        $project = $this->project($user);
        $boq = Boq::factory()->create(['project_id' => $project->id, 'organisation_id' => $user->organisation_id, 'status' => 'approved', 'currency' => 'UGX']);
        $item = BoqItem::factory()->create(['boq_id' => $boq->id, 'status' => 'approved', 'quantity' => 10, 'approved_rate' => 10, 'currency' => 'UGX']);
        $expense = Expense::factory()->create(['organisation_id' => $user->organisation_id, 'project_id' => $project->id, 'creator_user_id' => $user->id, 'currency' => 'UGX', 'total' => 60, 'boq_id' => $boq->id, 'boq_item_id' => $item->id]);
        $expense->items()->create(['boq_id' => $boq->id, 'boq_item_id' => $item->id, 'description' => 'Cement', 'quantity' => 6, 'unit' => 'bags', 'rate' => 10, 'total' => 60]);
        // A foreign-currency expense must not be mixed into the project-currency totals.
        Expense::factory()->create(['organisation_id' => $user->organisation_id, 'project_id' => $project->id, 'creator_user_id' => $user->id, 'currency' => 'USD', 'total' => 500]);

        $breakdown = app(ProjectAccountingService::class)->breakdown($project);

        $this->assertSame('UGX', $breakdown['currency']);
        $this->assertSame(100.0, $breakdown['budget']);
        $this->assertSame(60.0, $breakdown['spent']);
        $this->assertSame(40.0, $breakdown['balance']);
        $this->assertSame(100.0, $breakdown['rows'][0]['budget']);
        $this->assertSame(60.0, $breakdown['rows'][0]['spent']);
        $this->assertSame(40.0, $breakdown['rows'][0]['balance']);
        $this->assertSame(0.0, $breakdown['unlinked_spent']);
        $this->assertArrayHasKey('USD', $breakdown['other_currency']->all());
    }

    public function test_page_lists_assigned_project_and_exports(): void
    {
        $user = $this->member();
        $project = $this->project($user);

        $this->actingAs($user)->get(route('reports.accounting'))->assertOk()->assertSee($project->name);

        Livewire::actingAs($user)->test(Accounting::class)
            ->assertSee($project->name)
            ->call('exportTables', 'csv')
            ->assertFileDownloaded();
    }

    public function test_user_without_report_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('reports.accounting'))->assertForbidden();
    }
}
