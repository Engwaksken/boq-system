<?php

namespace Tests\Feature;

use App\Livewire\Expenses\Index as Expenses;
use App\Livewire\Faqs;
use App\Livewire\Team\Assignments;
use App\Livewire\Team\Index as Team;
use App\Models\Expense;
use App\Models\Faq;
use App\Models\Invitation;
use App\Models\Organisation;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class UserWorkspaceFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function member(string $role = 'user', ?int $organisationId = null): User
    {
        $user = User::factory()->create($organisationId === null ? [] : ['organisation_id' => $organisationId]);
        $user->roles()->attach(Role::where('slug', $role)->value('id'), ['organisation_id' => $user->organisation_id]);

        return $user;
    }

    private function fillExpense($component, Project $project)
    {
        return $component->call('create')->set('project_id', $project->id)->set('purchase_date', '2026-10-01')
            ->set('description', 'Cement purchase')->set('quantity', '3')->set('unit', 'bags')->set('rate', '12.5')->set('currency', 'UGX');
    }

    public function test_user_sidebar_has_faqs_expenses_and_team_links_without_admin_management(): void
    {
        $user = $this->member();
        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee(route('faqs.index'), false)->assertSee(route('expenses.index'), false)
            ->assertSee(route('team.index'), false)->assertDontSee(route('admin.faqs'), false)
            ->assertDontSee(route('team.assignments'), false);
        $this->get('/faqs')->assertOk();
        $this->get('/expenses')->assertOk();
        $this->get('/team/invitations')->assertOk();
        $this->get('/team/assignments')->assertForbidden();
    }

    public function test_faqs_show_only_active_entries_support_search_and_escape_html(): void
    {
        Faq::create(['question' => 'How do I record expenses?', 'answer' => '<script>alert(1)</script>Use the expense page.', 'is_active' => true, 'sort_order' => 1]);
        Faq::create(['question' => 'Internal draft', 'answer' => 'Not published', 'is_active' => false, 'sort_order' => 0]);
        Faq::create(['question' => 'How do I import a BOQ?', 'answer' => 'Upload a spreadsheet.', 'is_active' => true, 'sort_order' => 2]);
        Livewire::actingAs($this->member())->test(Faqs::class)->assertSee('How do I record expenses?')
            ->assertDontSee('Internal draft')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)
            ->set('search', 'spreadsheet')->assertSee('How do I import a BOQ?')->assertDontSee('How do I record expenses?');
    }

    public function test_user_records_edits_and_uploads_a_private_receipt_for_assigned_project(): void
    {
        Storage::fake('local');
        config(['filesystems.disks.private' => null]);
        $user = $this->member();
        $project = Project::factory()->assignedTo($user)->create(['organisation_id' => $user->organisation_id]);
        $component = $this->fillExpense(Livewire::actingAs($user)->test(Expenses::class), $project)
            ->call('save')->assertHasNoErrors();
        $expense = Expense::firstOrFail();
        $this->assertSame('37.50', $expense->total);
        $this->assertSame($user->id, $expense->creator_user_id);
        $component->call('edit', $expense->id)->set('quantity', '4')->call('save')->assertHasNoErrors();
        $this->assertSame('50.00', $expense->fresh()->total);
        $component->set('receiptFile', UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf'))
            ->call('uploadReceipt')->assertHasNoErrors()->assertSee('receipt.pdf');
        $receipt = $expense->receipts()->firstOrFail();
        $this->assertSame('local', $receipt->storage_disk);
        Storage::disk('local')->assertExists($receipt->storage_path);
        $this->actingAs($user)->get(route('expense-receipts.download', $receipt))->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="receipt.pdf"');
        $this->actingAs($this->member('user', $user->organisation_id))->get(route('expense-receipts.download', $receipt))->assertForbidden();
    }

    public function test_unplanned_expense_requires_reason_and_receipt_rejects_unsafe_files(): void
    {
        $user = $this->member();
        $project = Project::factory()->assignedTo($user)->create(['organisation_id' => $user->organisation_id]);
        $component = $this->fillExpense(Livewire::actingAs($user)->test(Expenses::class), $project)->set('is_planned', false)
            ->call('save')->assertHasErrors('explanation')->set('explanation', 'Emergency repairs')->call('save')->assertHasNoErrors();
        $component->set('receiptFile', UploadedFile::fake()->create('unsafe.svg', 10, 'image/svg+xml'))
            ->call('uploadReceipt')->assertHasErrors('receiptFile');
    }

    public function test_expenses_hide_other_purchasers_and_reject_unassigned_projects_and_edit_ids(): void
    {
        $user = $this->member();
        $other = $this->member('user', $user->organisation_id);
        $assigned = Project::factory()->assignedTo($user)->create(['organisation_id' => $user->organisation_id]);
        $unassigned = Project::factory()->create(['organisation_id' => $user->organisation_id]);
        $foreignExpense = Expense::factory()->create(['project_id' => $assigned->id, 'organisation_id' => $user->organisation_id, 'creator_user_id' => $other->id, 'description' => 'Hidden purchase']);
        Livewire::actingAs($user)->test(Expenses::class)->assertDontSee('Hidden purchase')->call('edit', $foreignExpense->id)->assertForbidden();
        $this->fillExpense(Livewire::actingAs($user)->test(Expenses::class), $unassigned)->call('save')->assertNotFound();
        $this->assertSame(1, Expense::count());
    }

    public function test_unverified_users_can_read_faqs_and_accept_invitations_but_not_record_expenses(): void
    {
        $user = $this->member();
        $user->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($user)->get('/faqs')->assertOk();
        $this->get('/team/invitations')->assertOk();
        $this->get('/expenses')->assertRedirect(route('verification.notice'));
        Livewire::actingAs($user)->test(Expenses::class)->assertForbidden();
    }

    public function test_admin_creates_revises_and_revokes_invitation_with_server_supplied_roles(): void
    {
        $admin = $this->member('administrator');
        $component = Livewire::actingAs($admin)->test(Team::class)->call('create')->set('email', 'member@example.test')
            ->set('role_id', Role::where('slug', 'procurement-officer')->value('id'))->set('expires_at', now()->addWeek()->toISOString())
            ->call('save')->assertHasNoErrors();
        $token = $component->get('createdToken');
        $this->assertMatchesRegularExpression('/^\d{5}$/', $token);
        $invitation = Invitation::firstOrFail();
        $this->assertSame(\App\Models\Invitation::hashCode($token), $invitation->token_hash);
        $component->call('edit', $invitation->id)->set('email', 'updated@example.test')->call('save')->assertHasNoErrors()
            ->call('revoke', $invitation->id);
        $this->assertSame('updated@example.test', $invitation->fresh()->email);
        $this->assertSame($admin->id, $invitation->fresh()->revoked_by_user_id);
    }

    public function test_invitation_acceptance_is_single_use_and_drops_old_organisation_privileges(): void
    {
        $admin = $this->member('administrator');
        $invitee = $this->member('administrator');
        [$invitation, $token] = app(\App\Services\InvitationService::class)->create($admin, [
            'email' => $invitee->email, 'role_id' => Role::where('slug', 'user')->value('id'), 'expires_at' => now()->addDay()->toISOString(),
        ]);
        Livewire::actingAs($invitee)->test(Team::class)->set('acceptToken', $token)->call('accept')->assertHasNoErrors()->assertRedirect(route('dashboard'));
        $invitee->refresh();
        $this->assertSame($admin->organisation_id, $invitee->organisation_id);
        $this->assertFalse($invitee->hasRole('administrator'));
        $this->assertNotNull($invitation->fresh()->accepted_at);
        Livewire::actingAs($invitee)->test(Team::class)->set('acceptToken', $token)->call('accept')->assertHasErrors('acceptToken');
    }

    public function test_regular_users_cannot_manage_invitations_or_project_assignments(): void
    {
        $user = $this->member();
        Livewire::actingAs($user)->test(Team::class)->call('create')->assertForbidden();
        Livewire::actingAs($user)->test(Assignments::class)->assertForbidden();
    }

    public function test_admin_assigns_restores_and_revokes_project_access_without_changing_organisation_role(): void
    {
        $admin = $this->member('administrator');
        $user = $this->member('procurement-officer', $admin->organisation_id);
        $project = Project::factory()->create(['organisation_id' => $admin->organisation_id]);
        $component = Livewire::actingAs($admin)->test(Assignments::class)->set('projectId', $project->id)->set('userId', $user->id)
            ->set('role', 'procurement-officer')->call('save')->assertHasNoErrors();
        $assignment = $project->assignments()->firstOrFail();
        $this->assertTrue($project->isAccessibleTo($user));
        $component->call('revoke', $assignment->id);
        $this->assertFalse($project->isAccessibleTo($user));
        $component->set('projectId', $project->id)->set('userId', $user->id)->call('save')->assertHasNoErrors();
        $this->assertSame(1, $project->assignments()->withTrashed()->count());
        $this->assertTrue($project->isAccessibleTo($user));
        $this->assertFalse($user->hasRole('administrator'));
    }

    public function test_project_assignment_rejects_foreign_projects_and_foreign_members(): void
    {
        $admin = $this->member('administrator');
        $user = $this->member('user', $admin->organisation_id);
        $foreign = Project::factory()->create();
        Livewire::actingAs($admin)->test(Assignments::class)->set('projectId', $foreign->id)->set('userId', $user->id)->call('save')->assertNotFound();
        $project = Project::factory()->create(['organisation_id' => $admin->organisation_id]);
        Livewire::actingAs($admin)->test(Assignments::class)->set('projectId', $project->id)->set('userId', $this->member()->id)->call('save')->assertNotFound();
    }

    public function test_guest_is_redirected_from_user_workspace_features(): void
    {
        foreach (['/faqs', '/expenses', '/team/invitations', '/team/assignments'] as $path) {
            $this->get($path)->assertRedirect(route('login'));
        }
    }

    public function test_web_project_pages_show_only_assigned_projects_and_block_revoked_access(): void
    {
        $user = $this->member();
        $assigned = Project::factory()->assignedTo($user)->create(['organisation_id' => $user->organisation_id, 'name' => 'Assigned clinic']);
        $hidden = Project::factory()->create(['organisation_id' => $user->organisation_id, 'name' => 'Unassigned road']);
        $this->actingAs($user)->get('/projects')->assertOk()->assertSee('Assigned clinic')->assertDontSee('Unassigned road');
        $this->get(route('projects.show', $assigned))->assertOk()->assertSee(route('expenses.index', ['project' => $assigned->id]), false);
        $this->get(route('projects.show', $hidden))->assertForbidden();
        $this->get(route('projects.edit', $hidden))->assertForbidden();
        $component = Livewire::actingAs($user)->test(\App\Livewire\Projects\Edit::class, ['project' => $assigned])->set('name', 'Unauthorized rename');
        $assigned->assignments()->first()->delete();
        $component->call('save')->assertForbidden();
        $this->assertSame('Assigned clinic', $assigned->fresh()->name);
    }

    public function test_web_project_totals_and_boqs_respect_view_permission_and_boq_tenant(): void
    {
        $user = User::factory()->create();
        $user->permissions()->attach(\App\Models\Permission::where('slug', 'projects.view')->value('id'));
        $project = Project::factory()->assignedTo($user)->create(['organisation_id' => $user->organisation_id]);
        $own = \App\Models\Boq::factory()->create(['project_id' => $project->id, 'organisation_id' => $user->organisation_id, 'name' => 'Visible BOQ']);
        $foreign = \App\Models\Boq::factory()->create(['project_id' => $project->id, 'name' => 'Foreign BOQ']);
        \App\Models\BoqItem::factory()->create(['boq_id' => $own->id, 'quantity' => 2, 'original_rate' => 10]);
        \App\Models\BoqItem::factory()->create(['boq_id' => $foreign->id, 'quantity' => 20, 'original_rate' => 999]);
        Livewire::actingAs($user)->test(\App\Livewire\Projects\Show::class, ['project' => $project])
            ->assertDontSee('Visible BOQ')->assertDontSee('Foreign BOQ')->assertViewHas('totals', fn ($totals) => $totals['estimated_amount'] === 0.0);
        $user->permissions()->attach(\App\Models\Permission::where('slug', 'boq.view')->value('id'));
        Livewire::actingAs($user)->test(\App\Livewire\Projects\Show::class, ['project' => $project])
            ->assertSee('Visible BOQ')->assertDontSee('Foreign BOQ')->assertViewHas('totals', fn ($totals) => $totals['estimated_amount'] === 20.0);
    }

    public function test_receipts_are_reviewed_and_saved_separately_without_losing_corrections(): void
    {
        Storage::fake('local');
        config(['filesystems.disks.private' => null, 'services.ai_provider' => 'gemini', 'services.gemini.key' => 'test']);
        $fields = ['supplier' => 'Shop', 'purchase_date' => '2026-10-01', 'description' => 'Cement',
            'quantity' => 2, 'unit' => 'bags', 'rate' => 10, 'total' => 20, 'currency' => 'UGX', 'payment_method' => 'cash', 'warnings' => []];
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::sequence()
            ->push(['candidates' => [['content' => ['parts' => [['text' => json_encode($fields)]]]]]])
            ->push(['candidates' => [['content' => ['parts' => [['text' => json_encode(array_replace($fields, ['supplier' => 'Other shop', 'description' => 'Sand']))]]]]]])]);
        $user = $this->member();
        $project = Project::factory()->assignedTo($user)->create(['organisation_id' => $user->organisation_id]);
        $component = Livewire::actingAs($user)->test(Expenses::class)->call('create')->set('project_id', $project->id)
            ->set('extractFiles', [
                UploadedFile::fake()->createWithContent('first.pdf', "%PDF-1.4\nfirst receipt\n%%EOF"),
                UploadedFile::fake()->createWithContent('second.pdf', "%PDF-1.4\nsecond receipt\n%%EOF"),
            ])->assertSet('description', 'Cement')->set('supplier', 'Corrected shop')
            ->call('reviewReceipt', 1)->assertSet('description', 'Sand')
            ->call('reviewReceipt', 0)->assertSet('supplier', 'Corrected shop')
            ->call('save')->assertHasNoErrors()->assertSet('showForm', true)->assertSet('description', 'Sand')
            ->call('save')->assertHasNoErrors()->assertSet('showForm', false);
        $expenses = Expense::with('receipts')->orderBy('id')->get();
        $this->assertCount(2, $expenses);
        $this->assertSame('Corrected shop', $expenses[0]->supplier);
        $this->assertSame('first.pdf', $expenses[0]->receipts->sole()->original_filename);
        $this->assertSame('second.pdf', $expenses[1]->receipts->sole()->original_filename);
        \Illuminate\Support\Facades\Http::assertSentCount(2);
    }

    public function test_expense_form_saves_boq_links_and_shows_over_budget_progress(): void
    {
        $user = $this->member();
        $project = Project::factory()->assignedTo($user)->create(['organisation_id' => $user->organisation_id]);
        $boq = \App\Models\Boq::factory()->create(['project_id' => $project->id, 'organisation_id' => $user->organisation_id, 'status' => 'approved']);
        $item = \App\Models\BoqItem::factory()->create(['boq_id' => $boq->id, 'status' => 'approved', 'quantity' => 2, 'approved_rate' => 10, 'unit' => 'bags', 'currency' => 'UGX']);
        $this->fillExpense(Livewire::actingAs($user)->test(Expenses::class), $project)
            ->set('boq_item_id', $item->id)->assertSee('Over budget')->call('save')->assertHasNoErrors()->assertSee('BOQ budget progress');
        $expense = Expense::with('items')->firstOrFail();
        $this->assertSame($item->id, $expense->items->sole()->boq_item_id);
        $this->assertSame($boq->id, $expense->items->sole()->boq_id);
    }

    public function test_expense_form_offers_payment_method_options(): void
    {
        $user = $this->member();
        $project = Project::factory()->assignedTo($user)->create(['organisation_id' => $user->organisation_id]);
        $this->fillExpense(Livewire::actingAs($user)->test(Expenses::class), $project)
            ->assertSee('Select payment method')
            ->set('payment_method', 'Mobile Money')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Mobile Money', Expense::firstOrFail()->payment_method);
    }

    public function test_multiple_receipts_can_be_attached_to_an_existing_expense(): void
    {
        Storage::fake('local');
        config(['filesystems.disks.private' => null]);
        $user = $this->member();
        $project = Project::factory()->assignedTo($user)->create(['organisation_id' => $user->organisation_id]);
        $component = $this->fillExpense(Livewire::actingAs($user)->test(Expenses::class), $project)->call('save');
        $component->set('receiptFiles', [
            UploadedFile::fake()->createWithContent('first.pdf', "%PDF-1.4\nfirst\n%%EOF"),
            UploadedFile::fake()->createWithContent('second.pdf', "%PDF-1.4\nsecond\n%%EOF"),
        ])->call('uploadReceipt')->assertHasNoErrors()->assertSee('first.pdf')->assertSee('second.pdf');
        $this->assertSame(2, Expense::firstOrFail()->receipts()->count());
        $this->assertSame('37.50', Expense::firstOrFail()->total);
    }
}
