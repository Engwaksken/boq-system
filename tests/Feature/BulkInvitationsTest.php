<?php

namespace Tests\Feature;

use App\Livewire\Team\Index;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\User;
use App\Services\BulkInvitationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class BulkInvitationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Mail::fake();
    }

    private function member(string $role = 'administrator'): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', $role)->value('id'), ['organisation_id' => $user->organisation_id]);

        return $user;
    }

    public function test_admin_downloads_template_and_imports_multiple_tenant_invitations(): void
    {
        $admin = $this->member();
        $component = Livewire::actingAs($admin)->test(Index::class)->call('downloadBulkTemplate')->assertFileDownloaded('team-invitations-template.csv')
            ->set('bulkFile', UploadedFile::fake()->createWithContent('team.csv', "email,role\nfirst@example.com,user\nsecond@example.com,finance\n"))
            ->call('uploadBulkInvitations')->assertHasNoErrors()->assertSee('first@example.com')->assertSee('second@example.com');
        $this->assertSame(2, Invitation::where('organisation_id', $admin->organisation_id)->count());
        $this->assertCount(2, $component->get('bulkResults'));
        $this->assertMatchesRegularExpression('/^\d{5}$/', $component->get('bulkResults')[0]['code']);
        Mail::assertSent(\App\Mail\TeamInvitation::class, 2);
        $component->set('bulkFile', UploadedFile::fake()->createWithContent('team.csv', "email,role\nfirst@example.com,user\n"))
            ->call('uploadBulkInvitations')->assertHasNoErrors()->assertSet('bulkResults.0.status', 'Already invited');
        $this->assertSame(2, Invitation::count());
    }

    public function test_all_rows_are_validated_before_any_invitation_is_created(): void
    {
        $admin = $this->member();
        try {
            app(BulkInvitationService::class)->import($admin, UploadedFile::fake()->createWithContent('team.csv', "email,role\nvalid@example.com,user\nother@example.com,super-admin\n"));
            $this->fail('Privileged roles must not be imported.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Row 3', $exception->errors()['file'][0]);
        }
        $this->assertSame(0, Invitation::count());
        Mail::assertNothingSent();
        Livewire::actingAs($this->member('user'))->test(Index::class)->call('downloadBulkTemplate')->assertForbidden();
    }

    public function test_admin_can_bulk_disable_selected_pending_invitations(): void
    {
        $admin = $this->member();
        $roleId = Role::where('slug', 'user')->value('id');
        [$first] = app(\App\Services\InvitationService::class)->create($admin, ['email' => 'first@example.com', 'role_id' => $roleId]);
        [$second] = app(\App\Services\InvitationService::class)->create($admin, ['email' => 'second@example.com', 'role_id' => $roleId]);

        Livewire::actingAs($admin)->test(Index::class)
            ->set('selected', [(string) $first->id, (string) $second->id])
            ->call('bulkRevoke')
            ->assertHasNoErrors()
            ->assertSet('selected', []);

        $this->assertNotNull($first->fresh()->revoked_at);
        $this->assertSame($admin->id, $first->fresh()->revoked_by_user_id);
        $this->assertNotNull($second->fresh()->revoked_at);
    }

    public function test_non_manager_lands_on_the_accept_tab_and_cannot_revoke(): void
    {
        $user = $this->member('user');
        Livewire::actingAs($user)->test(Index::class)
            ->assertSet('tab', 'accept')
            ->assertSee('Accept invitation')
            ->call('bulkRevoke')
            ->assertForbidden();
    }
}
