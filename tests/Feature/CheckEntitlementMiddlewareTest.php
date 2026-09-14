<?php

namespace Tests\Feature;

use App\Models\Entitlement;
use App\Models\Feature;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CheckEntitlementMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'entitlement:boq.management'])
            ->get('/api/v1/_test/entitlement', fn () => response()->json(['success' => true]));

        Route::middleware(['auth:sanctum', 'entitlement:boq.import.excel,boq_imports'])
            ->get('/api/v1/_test/entitlement/import-success', fn () => response()->json(['success' => true]));

        Route::middleware(['auth:sanctum', 'entitlement:boq.import.excel,boq_imports'])
            ->get('/api/v1/_test/entitlement/import-failure', fn () => response()->json(['success' => false], 422));
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/_test/entitlement');

        $response->assertStatus(401);
    }

    public function test_super_admin_bypasses_entitlement_check(): void
    {
        $superAdminRole = Role::factory()->create(['slug' => 'super-admin']);
        $user = User::factory()->create();
        $user->roles()->attach($superAdminRole);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/_test/entitlement');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_denies_without_entitlement(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/_test/entitlement');

        $response->assertStatus(403)
            ->assertJson(['error_code' => 'FEATURE_TOPUP_REQUIRED']);
    }

    public function test_allows_with_valid_entitlement(): void
    {
        $user = User::factory()->create();
        $feature = Feature::factory()->create(['code' => 'boq.management']);
        $plan = Plan::factory()->create();
        $subscription = Subscription::factory()->create(['plan_id' => $plan->id]);
        $subscription->forceFill([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
        ])->save();

        Entitlement::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'subscription_id' => $subscription->id,
            'feature_id' => $feature->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'expires_at' => now()->addDays(10),
            'is_permanent' => false,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/_test/entitlement');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_denies_with_expired_entitlement(): void
    {
        $user = User::factory()->create();
        $feature = Feature::factory()->create(['code' => 'boq.management']);
        $plan = Plan::factory()->create();
        $subscription = Subscription::factory()->create(['plan_id' => $plan->id]);
        $subscription->forceFill([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
        ])->save();

        Entitlement::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'subscription_id' => $subscription->id,
            'feature_id' => $feature->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'expires_at' => now()->subDay(),
            'is_permanent' => false,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/_test/entitlement');

        $response->assertStatus(403)
            ->assertJson(['error_code' => 'FEATURE_TOPUP_REQUIRED']);
    }

    public function test_user_without_an_organisation_cannot_use_another_users_null_organisation_entitlement(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['organisation_id' => null])->save();
        $feature = Feature::factory()->create(['code' => 'boq.management']);

        Entitlement::factory()->create([
            'user_id' => User::factory(),
            'organisation_id' => null,
            'feature_id' => $feature->id,
            'status' => 'active',
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/_test/entitlement')
            ->assertStatus(403)
            ->assertJson(['error_code' => 'FEATURE_TOPUP_REQUIRED']);
    }

    public function test_denies_an_entitlement_with_exhausted_import_usage(): void
    {
        $user = User::factory()->create();
        $this->grantImportEntitlement($user, ['boq_imports' => 1], ['boq_imports' => 1]);

        $this->actingAs($user)
            ->getJson('/api/v1/_test/entitlement/import-success')
            ->assertStatus(403)
            ->assertJson(['error_code' => 'FEATURE_TOPUP_REQUIRED']);
    }

    public function test_import_usage_is_incremented_after_a_successful_response(): void
    {
        $user = User::factory()->create();
        $entitlement = $this->grantImportEntitlement($user, ['boq_imports' => 2]);

        $this->actingAs($user)
            ->getJson('/api/v1/_test/entitlement/import-success')
            ->assertOk();

        $this->assertSame(1, $entitlement->fresh()->usage['boq_imports']);
    }

    public function test_import_usage_is_not_incremented_after_an_unsuccessful_response(): void
    {
        $user = User::factory()->create();
        $entitlement = $this->grantImportEntitlement($user, ['boq_imports' => 2]);

        $this->actingAs($user)
            ->getJson('/api/v1/_test/entitlement/import-failure')
            ->assertStatus(422);

        $this->assertNull($entitlement->fresh()->usage);
    }

    private function grantImportEntitlement(User $user, array $limits, array $usage = []): Entitlement
    {
        return Entitlement::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'feature_id' => Feature::factory()->create(['code' => 'boq.import.excel'])->id,
            'status' => 'active',
            'expires_at' => now()->addDay(),
            'limits' => $limits,
            'usage' => $usage ?: null,
        ]);
    }
}
