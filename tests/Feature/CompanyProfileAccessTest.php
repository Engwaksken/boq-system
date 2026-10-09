<?php

namespace Tests\Feature;

use App\Livewire\Profile\Index as ProfileIndex;
use App\Models\Organisation;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CompanyProfileAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function user(string $role, ?int $organisationId): User
    {
        $user = User::factory()->create(['organisation_id' => $organisationId]);
        $user->roles()->attach(Role::where('slug', $role)->value('id'), ['organisation_id' => $organisationId]);

        return $user;
    }

    public function test_invited_member_cannot_see_or_save_the_company_profile(): void
    {
        $org = Organisation::factory()->create();
        $member = $this->user('project-manager', $org->id);

        Livewire::actingAs($member)->test(ProfileIndex::class)
            ->assertDontSee('Company Profile')
            ->call('saveCompanyProfile')
            ->assertForbidden();
    }

    public function test_administrator_and_personal_owner_can_manage_the_company_profile(): void
    {
        $org = Organisation::factory()->create();
        $admin = $this->user('administrator', $org->id);
        Livewire::actingAs($admin)->test(ProfileIndex::class)->assertSee('Company Profile');

        // The organisation owner role (customer) may also manage it.
        $owner = $this->user('user', $org->id);
        Livewire::actingAs($owner)->test(ProfileIndex::class)->assertSee('Company Profile');

        $personal = User::factory()->create();
        $personal->forceFill(['organisation_id' => null])->save();
        Livewire::actingAs($personal)->test(ProfileIndex::class)->assertSee('Company Profile');
    }
}
