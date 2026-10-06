<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseReceipt;
use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\Invitation;
use App\Models\Organisation;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Mail\TeamInvitation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ExpenseInvitationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function user(Organisation $organisation, string $role = 'user', bool $verified = true): User
    {
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'email_verified_at' => $verified ? now() : null,
        ]);
        $user->roles()->attach(Role::where('slug', $role)->value('id'), ['organisation_id' => $organisation->id]);
        return $user;
    }

    private function payload(Project $project, array $extra = []): array
    {
        return array_merge([
            'project_id' => $project->id, 'purchase_date' => '2026-10-01', 'supplier' => 'Vendor',
            'description' => 'Cement purchase', 'quantity' => 2, 'unit' => 'bags', 'rate' => 10,
            'total' => 20, 'currency' => 'UGX', 'is_planned' => true,
        ], $extra);
    }

    private function assignToProject(Project $project, User $user): void
    {
        $project->assignments()->create([
            'user_id' => $user->id,
            'role' => 'project-manager',
            'assigned_by' => $user->id,
        ]);
    }

    public function test_user_can_create_and_read_an_expense(): void
    {
        $org = Organisation::factory()->create();
        $user = $this->user($org);
        $project = Project::factory()->create(['organisation_id' => $org->id]);
        $this->assignToProject($project, $user);
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/expenses', $this->payload($project));
        $response->assertCreated()->assertJsonPath('data.description', 'Cement purchase');
        $this->getJson('/api/v1/expenses/'.$response->json('data.id'))->assertOk();
    }

    public function test_expense_filters_and_currency_totals_respect_visibility(): void
    {
        $org = Organisation::factory()->create();
        $user = $this->user($org);
        $project = Project::factory()->assignedTo($user)->create(['organisation_id' => $org->id]);
        $otherProject = Project::factory()->assignedTo($user)->create(['organisation_id' => $org->id]);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/expenses', $this->payload($project))->assertCreated();
        $this->postJson('/api/v1/expenses', $this->payload($otherProject, ['rate' => 50]))->assertCreated();
        $this->postJson('/api/v1/expenses', $this->payload($project, ['description' => 'Sand', 'currency' => 'USD']))->assertCreated();
        $response = $this->getJson('/api/v1/expenses?project_id='.$project->id.'&search=cement')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonCount(1, 'totals')->assertJsonPath('totals.0.currency', 'UGX');
        $this->assertEquals(20, $response->json('totals.0.amount'));
        $this->getJson('/api/v1/expenses?project_id=999999')->assertOk()->assertJsonCount(0, 'totals');
    }

    public function test_expense_project_lookup_excludes_unassigned_foreign_and_revoked_assignments(): void
    {
        $org = Organisation::factory()->create();
        $user = $this->user($org);
        $assigned = Project::factory()->assignedTo($user)->create(['organisation_id' => $org->id]);
        Project::factory()->create(['organisation_id' => $org->id]);
        Project::factory()->assignedTo($user)->create();
        $revoked = Project::factory()->assignedTo($user)->create(['organisation_id' => $org->id]);
        $revoked->assignments()->first()->delete();

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/expenses/projects')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $assigned->id);
    }

    public function test_invitation_role_lookup_is_admin_only_and_excludes_privileged_roles(): void
    {
        $org = Organisation::factory()->create();
        $this->actingAs($this->user($org), 'sanctum')->getJson('/api/v1/invitations/roles')->assertForbidden();
        $this->actingAs($this->user($org, 'administrator'), 'sanctum')
            ->getJson('/api/v1/invitations/roles')->assertOk()->assertJsonCount(4, 'data')
            ->assertJsonMissing(['slug' => 'super-admin'])->assertJsonMissing(['slug' => 'administrator']);
    }

    public function test_expense_total_is_derived_and_client_identity_fields_are_ignored(): void
    {
        $org = Organisation::factory()->create();
        $user = $this->user($org);
        $otherOrg = Organisation::factory()->create();
        $other = $this->user($otherOrg);
        $project = Project::factory()->create(['organisation_id' => $org->id]);
        $this->assignToProject($project, $user);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/expenses', $this->payload($project, [
            'quantity' => 3, 'rate' => 12.5, 'total' => 1,
            'organisation_id' => $otherOrg->id, 'creator_user_id' => $other->id,
            'purchaser_user_id' => $other->id,
        ]));

        $response->assertCreated()->assertJsonPath('data.total', '37.50');
        $this->assertDatabaseHas('expenses', [
            'id' => $response->json('data.id'),
            'organisation_id' => $org->id,
            'creator_user_id' => $user->id,
            'purchaser_user_id' => $user->id,
        ]);
    }

    public function test_unplanned_expense_requires_explanation(): void
    {
        $org = Organisation::factory()->create();
        $user = $this->user($org);
        $project = Project::factory()->create(['organisation_id' => $org->id]);
        $this->assignToProject($project, $user);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/expenses', $this->payload($project, ['is_planned' => false]))
            ->assertUnprocessable()->assertJsonValidationErrors('explanation');
    }

    public function test_duplicate_expense_creation_is_rejected(): void
    {
        $org = Organisation::factory()->create();
        $user = $this->user($org);
        $project = Project::factory()->create(['organisation_id' => $org->id]);
        $this->assignToProject($project, $user);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/expenses', $this->payload($project))->assertCreated();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/expenses', $this->payload($project))->assertConflict();
        $this->assertSame(1, Expense::where('project_id', $project->id)->count());
    }

    public function test_one_expense_can_contain_multiple_items_and_sums_the_line_totals(): void
    {
        $org = Organisation::factory()->create();
        $user = $this->user($org);
        $project = Project::factory()->create(['organisation_id' => $org->id]);
        $this->assignToProject($project, $user);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/expenses', [
            'project_id' => $project->id,
            'purchase_date' => '2026-10-01',
            'supplier' => 'Vendor',
            'currency' => 'UGX',
            'is_planned' => true,
            'items' => [
                ['description' => 'Cement', 'quantity' => 2, 'unit' => 'bags', 'rate' => 1500],
                ['description' => 'Sand', 'quantity' => 3, 'unit' => 'm3', 'rate' => 400],
            ],
        ]);

        $response->assertCreated()->assertJsonPath('data.total', '4200.00');
        $this->assertDatabaseCount('expense_items', 2);
        $this->assertDatabaseHas('expense_items', ['description' => 'Cement', 'total' => 3000]);
        $this->assertDatabaseHas('expense_items', ['description' => 'Sand', 'total' => 1200]);
    }

    public function test_expense_links_boq_and_item_that_belong_to_the_project(): void
    {
        $org = Organisation::factory()->create();
        $user = $this->user($org);
        $project = Project::factory()->create(['organisation_id' => $org->id]);
        $this->assignToProject($project, $user);
        $boq = Boq::factory()->create(['project_id' => $project->id, 'organisation_id' => $org->id]);
        $item = BoqItem::factory()->create(['boq_id' => $boq->id]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/expenses', $this->payload($project, [
            'boq_id' => $boq->id, 'boq_item_id' => $item->id,
        ]));

        $response->assertCreated()->assertJsonPath('data.project_id', $project->id);
        $this->assertDatabaseHas('expenses', ['id' => $response->json('data.id'), 'boq_id' => $boq->id, 'boq_item_id' => $item->id]);
    }

    public function test_expense_rejects_boq_from_another_project(): void
    {
        $org = Organisation::factory()->create();
        $user = $this->user($org);
        $project = Project::factory()->create(['organisation_id' => $org->id]);
        $otherProject = Project::factory()->create(['organisation_id' => $org->id]);
        $this->assignToProject($project, $user);
        $foreignBoq = Boq::factory()->create(['project_id' => $otherProject->id, 'organisation_id' => $org->id]);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/expenses', $this->payload($project, [
            'boq_id' => $foreignBoq->id,
        ]))->assertUnprocessable();
    }

    public function test_expense_rejects_boq_item_from_another_boq(): void
    {
        $org = Organisation::factory()->create();
        $user = $this->user($org);
        $project = Project::factory()->create(['organisation_id' => $org->id]);
        $this->assignToProject($project, $user);
        $boq = Boq::factory()->create(['project_id' => $project->id, 'organisation_id' => $org->id]);
        $otherBoq = Boq::factory()->create(['project_id' => $project->id, 'organisation_id' => $org->id]);
        $foreignItem = BoqItem::factory()->create(['boq_id' => $otherBoq->id]);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/expenses', $this->payload($project, [
            'boq_id' => $boq->id, 'boq_item_id' => $foreignItem->id,
        ]))->assertUnprocessable();
    }

    public function test_expense_isolation_rejects_foreign_project_and_hides_foreign_expense(): void
    {
        $org = Organisation::factory()->create();
        $foreignOrg = Organisation::factory()->create();
        $user = $this->user($org);
        $foreignProject = Project::factory()->create(['organisation_id' => $foreignOrg->id]);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/expenses', $this->payload($foreignProject))->assertNotFound();
        $foreignExpense = Expense::factory()->create(['organisation_id' => $foreignOrg->id, 'project_id' => $foreignProject->id, 'creator_user_id' => $user->id]);
        $this->getJson('/api/v1/expenses/'.$foreignExpense->id)->assertForbidden();
    }

    public function test_private_receipt_is_downloadable_only_by_expense_participant(): void
    {
        Storage::fake('local');
        $org = Organisation::factory()->create();
        $owner = $this->user($org);
        $other = $this->user($org);
        $project = Project::factory()->create(['organisation_id' => $org->id]);
        $expense = Expense::factory()->create(['organisation_id' => $org->id, 'project_id' => $project->id, 'creator_user_id' => $owner->id]);
        $path = 'expense-receipts/private.pdf';
        Storage::disk('local')->put($path, 'receipt');
        $receipt = ExpenseReceipt::factory()->create(['expense_id' => $expense->id, 'uploaded_by_user_id' => $owner->id, 'storage_path' => $path]);
        $this->actingAs($other, 'sanctum')->getJson('/api/v1/expense-receipts/'.$receipt->id.'/download')->assertForbidden();
    }

    public function test_receipt_upload_is_stored_privately_and_downloaded_as_attachment(): void
    {
        Storage::fake('local');
        config(['filesystems.disks.private' => null]);
        $org = Organisation::factory()->create();
        $owner = $this->user($org);
        $project = Project::factory()->create(['organisation_id' => $org->id]);
        $expense = Expense::factory()->create(['organisation_id' => $org->id, 'project_id' => $project->id, 'creator_user_id' => $owner->id]);
        $this->assignToProject($project, $owner);

        $uploaded = $this->actingAs($owner, 'sanctum')->post('/api/v1/expenses/'.$expense->id.'/receipts', [
            'file' => UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf'),
        ]);
        $uploaded->assertCreated();
        $receiptId = $uploaded->json('data.id');
        $receipt = ExpenseReceipt::findOrFail($receiptId);
        $this->assertSame('local', $receipt->storage_disk);
        Storage::disk('local')->assertExists($receipt->storage_path);
        $this->get('/api/v1/expense-receipts/'.$receiptId.'/download')->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="receipt.pdf"');
    }

    public function test_receipt_upload_rejects_disallowed_mime_type(): void
    {
        $org = Organisation::factory()->create();
        $owner = $this->user($org);
        $project = Project::factory()->create(['organisation_id' => $org->id]);
        $expense = Expense::factory()->create(['organisation_id' => $org->id, 'project_id' => $project->id, 'creator_user_id' => $owner->id]);
        $this->assignToProject($project, $owner);

        $this->actingAs($owner, 'sanctum')->post('/api/v1/expenses/'.$expense->id.'/receipts', [
            'file' => UploadedFile::fake()->create('receipt.txt', 10, 'text/plain'),
        ])->assertUnprocessable();
    }

    public function test_duplicate_receipt_upload_is_rejected_by_content_hash(): void
    {
        Storage::fake('local');
        config(['filesystems.disks.private' => null]);
        $org = Organisation::factory()->create();
        $owner = $this->user($org);
        $project = Project::factory()->create(['organisation_id' => $org->id]);
        $expense = Expense::factory()->create(['organisation_id' => $org->id, 'project_id' => $project->id, 'creator_user_id' => $owner->id]);
        $this->assignToProject($project, $owner);

        $this->actingAs($owner, 'sanctum')->post('/api/v1/expenses/'.$expense->id.'/receipts', [
            'file' => UploadedFile::fake()->createWithContent('same.pdf', "%PDF-1.4\nidentical receipt"),
        ])->assertCreated();

        $this->actingAs($owner, 'sanctum')->post('/api/v1/expenses/'.$expense->id.'/receipts', [
            'file' => UploadedFile::fake()->createWithContent('renamed.pdf', "%PDF-1.4\nidentical receipt"),
        ])->assertConflict();

        $this->assertSame(1, $expense->receipts()->count());
        $this->assertSame(1, ExpenseReceipt::where('expense_id', $expense->id)->whereNotNull('sha256')->count());
    }

    public function test_only_administrator_can_create_invitation(): void
    {
        $org = Organisation::factory()->create();
        $user = $this->user($org);
        $roleId = Role::where('slug', 'user')->value('id');
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/invitations', ['email' => 'invite@example.test', 'role_id' => $roleId, 'expires_at' => now()->addDays(3)->toISOString()])->assertForbidden();
    }

    public function test_administrator_can_create_invitation(): void
    {
        Mail::fake();
        $org = Organisation::factory()->create();
        $admin = $this->user($org, 'administrator');
        $roleId = Role::where('slug', 'user')->value('id');
        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/invitations', ['email' => 'invite@example.test', 'role_id' => $roleId, 'expires_at' => now()->addDays(3)->toISOString()])->assertCreated();
        $this->assertFalse($response->json('email_sent'), 'The log mailer records email but does not deliver it.');
        Mail::assertSent(TeamInvitation::class, fn (TeamInvitation $mail) => $mail->hasTo('invite@example.test') && preg_match('/^\d{5}$/', $mail->code) === 1);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $admin->id,
            'type' => 'team_invitation',
            'title' => 'Invitation created — email not sent',
        ]);
    }

    public function test_created_invitation_expires_six_hours_after_creation_and_keeps_token_hash_private(): void
    {
        $org = Organisation::factory()->create();
        $admin = $this->user($org, 'administrator');
        $roleId = Role::where('slug', 'user')->value('id');

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/invitations', [
            'email' => 'six-hours@example.test', 'role_id' => $roleId,
            'expires_at' => now()->addDays(3)->toISOString(),
        ])->assertCreated();

        $invitation = Invitation::where('email', 'six-hours@example.test')->firstOrFail();
        $this->assertEqualsWithDelta(now()->addHours(6)->timestamp, $invitation->expires_at->timestamp, 2);
        $this->assertArrayNotHasKey('token_hash', $response->json('data'));
    }

    public function test_accepting_another_organisation_invitation_does_not_carry_old_privileges(): void
    {
        $oldOrg = Organisation::factory()->create();
        $newOrg = Organisation::factory()->create();
        $invitee = $this->user($oldOrg, 'administrator');
        $invitee->permissions()->attach(\App\Models\Permission::where('slug', 'hardware-prices.manage')->value('id'));
        $inviter = $this->user($newOrg, 'administrator');
        $roleId = Role::where('slug', 'user')->value('id');
        $token = $this->actingAs($inviter, 'sanctum')->postJson('/api/v1/invitations', [
            'email' => $invitee->email, 'role_id' => $roleId, 'expires_at' => now()->addDay()->toISOString(),
        ])->assertCreated()->json('token');
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $invitee->id,
            'type' => 'team_invitation',
            'title' => 'You have a team invitation',
        ]);

        $this->actingAs($invitee, 'sanctum')->postJson('/api/v1/invitations/accept', ['token' => $token])->assertOk();
        $invitee->refresh();
        $this->assertSame($newOrg->id, $invitee->organisation_id);
        $this->assertFalse($invitee->hasRole('administrator'));
        $this->assertFalse($invitee->hasPermission('hardware-prices.manage'));
        $this->assertSame(0, $invitee->permissions()->count());
        $this->assertSame(1, $invitee->roles()->wherePivot('organisation_id', $newOrg->id)->count());
        $unassigned = Project::factory()->create(['organisation_id' => $newOrg->id]);
        $this->assertFalse($unassigned->isAccessibleTo($invitee));
    }

    public function test_administrator_can_invite_each_supported_role(): void
    {
        $org = Organisation::factory()->create();
        $admin = $this->user($org, 'administrator');

        foreach (['project-manager', 'procurement-officer', 'finance', 'user'] as $slug) {
            $roleId = Role::where('slug', $slug)->value('id');
            $this->actingAs($admin, 'sanctum')->postJson('/api/v1/invitations', [
                'email' => $slug.'@example.test', 'role_id' => $roleId,
                'expires_at' => now()->addDays(3)->toISOString(),
            ])->assertCreated();
        }
    }

    public function test_administrator_cannot_invite_owner_or_administrator_roles(): void
    {
        $org = Organisation::factory()->create();
        $admin = $this->user($org, 'administrator');

        foreach (['owner', 'administrator'] as $slug) {
            $roleId = Role::where('slug', $slug)->value('id');
            $this->actingAs($admin, 'sanctum')->postJson('/api/v1/invitations', [
                'email' => $slug.'@example.test', 'role_id' => $roleId,
                'expires_at' => now()->addDays(3)->toISOString(),
            ])->assertUnprocessable();
        }
    }

    public function test_verified_middleware_blocks_unverified_user_but_allows_exception_route(): void
    {
        $org = Organisation::factory()->create();
        $user = $this->user($org, 'administrator', false);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/expenses')->assertForbidden();
        $this->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_guest_cannot_access_expense_routes(): void
    {
        $this->getJson('/api/v1/expenses')->assertUnauthorized();
    }

    public function test_invitation_acceptance_is_single_use_and_assigns_invited_user(): void
    {
        $org = Organisation::factory()->create();
        $admin = $this->user($org, 'administrator');
        $invitee = User::factory()->create(['email' => 'invite@example.test', 'organisation_id' => null]);
        $roleId = Role::where('slug', 'user')->value('id');
        $created = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/invitations', [
            'email' => $invitee->email, 'role_id' => $roleId, 'expires_at' => now()->addDays(3)->toISOString(),
        ])->assertCreated();

        $token = $created->json('token');
        $this->assertMatchesRegularExpression('/^\d{5}$/', $token, 'Invitation codes are five digits.');
        $acceptance = $this->actingAs($invitee, 'sanctum')->postJson('/api/v1/invitations/accept', ['token' => $token]);
        if ($acceptance->status() !== 200) {
            fwrite(STDERR, "Invitation acceptance failed (HTTP {$acceptance->status()}): {$acceptance->getContent()}\n");
        }
        $acceptance->assertOk();
        $this->assertSame($org->id, $invitee->fresh()->organisation_id);
        $this->postJson('/api/v1/invitations/accept', ['token' => $token])->assertGone();
    }

    public function test_invitation_acceptance_rejects_wrong_email_and_revoked_token(): void
    {
        $org = Organisation::factory()->create();
        $admin = $this->user($org, 'administrator');
        $invitee = User::factory()->create(['email' => 'invite@example.test', 'organisation_id' => null]);
        $roleId = Role::where('slug', 'user')->value('id');
        $created = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/invitations', [
            'email' => $invitee->email, 'role_id' => $roleId, 'expires_at' => now()->addDays(3)->toISOString(),
        ])->assertCreated();
        $token = $created->json('token');

        $wrongUser = User::factory()->create(['email' => 'wrong@example.test']);
        $this->actingAs($wrongUser, 'sanctum')->postJson('/api/v1/invitations/accept', ['token' => $token])->assertForbidden();
        $invitation = Invitation::where('email', $invitee->email)->firstOrFail();
        $this->actingAs($admin, 'sanctum')->deleteJson('/api/v1/invitations/'.$invitation->id)->assertOk();
        $this->actingAs($invitee, 'sanctum')->postJson('/api/v1/invitations/accept', ['token' => $token])->assertGone();
    }

    public function test_guest_cannot_accept_invitation(): void
    {
        $this->postJson('/api/v1/invitations/accept', ['token' => 'token'])->assertUnauthorized();
    }
}
