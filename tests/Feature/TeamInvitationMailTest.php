<?php

namespace Tests\Feature;

use App\Mail\TeamInvitation;
use App\Models\Invitation;
use App\Models\Organisation;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamInvitationMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_invitation_email_is_branded_with_organisation_role_and_platform(): void
    {
        SiteSetting::set('system_name', 'Acme BOQ Cloud');
        $org = Organisation::factory()->create(['name' => 'Builders Ltd']);
        $inviter = User::factory()->create(['organisation_id' => $org->id, 'name' => 'Jane Doe']);
        $invitation = Invitation::factory()->create([
            'organisation_id' => $org->id,
            'email' => 'invitee@example.com',
            'role_id' => Role::where('slug', 'finance')->value('id'),
            'inviter_user_id' => $inviter->id,
        ]);

        $html = (new TeamInvitation($invitation, '12345', $inviter))->render();

        $this->assertStringContainsString('Builders Ltd', $html);
        $this->assertStringContainsString('Acme BOQ Cloud', $html);
        $this->assertStringContainsString('Finance', $html);
        $this->assertStringContainsString('Jane Doe', $html);
        $this->assertStringContainsString('12345', $html);
    }
}
