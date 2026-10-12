<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Rate;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The rate library is shared by every customer, so only super admins publish to it. */
class RateLibraryCurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(string $slug, ?int $organisationId = null): User
    {
        $user = User::factory()->create();
        $user->forceFill(['organisation_id' => $organisationId])->save();
        $user->roles()->attach(Role::where('slug', $slug)->value('id'), ['organisation_id' => $organisationId]);

        return $user->fresh();
    }

    private function payload(): array
    {
        return [
            'item' => 'Cement 42.5N (50kg)',
            'unit' => 'bag',
            'rate' => 35000,
            'currency' => 'UGX',
            'source_type' => 'manual',
            'verification_status' => 'approved',
        ];
    }

    public function test_organisation_submissions_wait_for_review(): void
    {
        $admin = $this->userWithRole('administrator', Organisation::factory()->create()->id);

        $this->actingAs($admin)->postJson('/api/v1/rates', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.verification_status', 'pending')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_super_admins_publish_directly(): void
    {
        $this->actingAs($this->userWithRole('super-admin'))->postJson('/api/v1/rates', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.verification_status', 'approved');
    }

    public function test_only_super_admins_approve_or_reject(): void
    {
        $admin = $this->userWithRole('administrator', Organisation::factory()->create()->id);
        $rate = Rate::factory()->create(['verification_status' => 'pending', 'is_active' => false, 'created_by' => $admin->id]);

        $this->actingAs($admin)->postJson("/api/v1/rates/{$rate->id}/approve")->assertForbidden();
        $this->actingAs($admin)->postJson("/api/v1/rates/{$rate->id}/reject")->assertForbidden();

        $this->actingAs($this->userWithRole('super-admin'))->postJson("/api/v1/rates/{$rate->id}/approve")->assertOk();
        $this->assertSame('approved', $rate->fresh()->verification_status);
    }

    public function test_repricing_a_published_rate_sends_it_back_for_review(): void
    {
        $admin = $this->userWithRole('administrator', Organisation::factory()->create()->id);
        $rate = Rate::factory()->create(['created_by' => $admin->id]);

        $this->actingAs($admin)->putJson("/api/v1/rates/{$rate->id}", ['rate' => 1])->assertOk();

        $rate->refresh();
        $this->assertSame('pending', $rate->verification_status);
        $this->assertFalse((bool) $rate->is_active);
    }
}
