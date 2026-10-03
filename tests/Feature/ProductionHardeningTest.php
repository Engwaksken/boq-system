<?php

namespace Tests\Feature;

use App\Http\Controllers\LegalPageController;
use App\Livewire\Admin\CurrenciesManager;
use App\Livewire\Admin\LanguagesManager;
use App\Livewire\Admin\PlansManager;
use App\Livewire\Admin\SubscriptionsManager;
use App\Models\Boq;
use App\Models\Currency;
use App\Models\Entitlement;
use App\Models\Feature;
use App\Models\HardwareCategory;
use App\Models\Language;
use App\Models\Organisation;
use App\Models\PaymentGateway;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductionHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['organisation_id' => Organisation::factory()->create()->id]);
        $user->roles()->attach($role);

        return $user;
    }

    // ---------------------------------------------------------------- auth UI

    public function test_login_password_field_is_masked_and_has_toggle_and_biometric_option(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('type="password"', false)
            ->assertDontSee('showPassword', false)
            ->assertSee('data-password-toggle', false)
            ->assertSee('data-biometric-login', false);
    }

    public function test_reset_and_confirm_password_pages_have_toggles(): void
    {
        $this->get('/reset-password/token123?email=a@b.test')
            ->assertOk()
            ->assertSee('data-password-toggle', false);

        $this->actingAs(User::factory()->create())
            ->get('/confirm-password')
            ->assertOk()
            ->assertSee('data-password-toggle', false);
    }

    public function test_webauthn_routes_are_registered(): void
    {
        $this->postJson('/webauthn/login/options')->assertOk()->assertJsonStructure(['challenge']);
    }

    // ---------------------------------------------------------------- legal HTML

    public function test_legal_pages_allow_html_but_strip_scripts(): void
    {
        SiteSetting::set('privacy_policy', '<h2>Data we keep</h2><p onclick="alert(1)">Safe <strong>text</strong></p><script>alert(2)</script><a href="javascript:alert(3)">x</a>');

        $response = $this->get(route('legal.privacy'))->assertOk();

        $response->assertSee('<h2>Data we keep</h2>', false)
            ->assertSee('<strong>text</strong>', false)
            ->assertDontSee('alert(2)', false)
            ->assertDontSee('onclick', false)
            ->assertDontSee('javascript:', false);
    }

    public function test_plain_text_legal_content_keeps_line_breaks(): void
    {
        $this->assertSame('Line one<br />'."\n".'Tools &amp; plant', (string) LegalPageController::toSafeHtml("Line one\nTools & plant"));
    }

    // ---------------------------------------------------------------- categories, currencies, languages

    public function test_building_categories_are_seeded_in_the_database(): void
    {
        $this->assertTrue(HardwareCategory::where('name', 'Sanitary Ware')->exists());
        $this->assertTrue(HardwareCategory::where('name', 'Labour')->exists());
        $this->assertGreaterThanOrEqual(25, HardwareCategory::count());
    }

    public function test_admin_can_add_currency_and_bulk_delete_but_never_the_default(): void
    {
        $admin = $this->superAdmin();
        $default = Currency::where('is_default', true)->firstOrFail();

        Livewire::actingAs($admin)
            ->test(CurrenciesManager::class)
            ->call('create')
            ->set('form.code', 'zar')
            ->set('form.name', 'South African Rand')
            ->set('form.symbol', 'R')
            ->call('save')
            ->assertHasNoErrors();

        $zar = Currency::where('code', 'ZAR')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(CurrenciesManager::class)
            ->set('selected', [(string) $zar->id, (string) $default->id])
            ->call('bulkDelete');

        $this->assertNull(Currency::find($zar->id));
        $this->assertNotNull(Currency::find($default->id));
        $this->assertContains('USD', Currency::activeCodes());
    }

    public function test_admin_can_add_language_and_set_it_as_default(): void
    {
        $admin = $this->superAdmin();

        Livewire::actingAs($admin)
            ->test(LanguagesManager::class)
            ->call('create')
            ->set('form.code', 'sw')
            ->set('form.name', 'Swahili')
            ->set('form.native_name', 'Kiswahili')
            ->call('save')
            ->assertHasNoErrors();

        $swahili = Language::where('code', 'sw')->firstOrFail();

        Livewire::actingAs($admin)->test(LanguagesManager::class)->call('setDefault', $swahili->id);

        $this->assertTrue($swahili->fresh()->is_default);
        $this->assertSame('sw', SiteSetting::get('language'));
        $this->assertSame(1, Language::where('is_default', true)->count());
    }

    public function test_currency_and_language_managers_are_super_admin_only(): void
    {
        Livewire::actingAs(User::factory()->create())->test(CurrenciesManager::class)->assertForbidden();
        Livewire::actingAs(User::factory()->create())->test(LanguagesManager::class)->assertForbidden();
    }

    // ---------------------------------------------------------------- bulk delete + subscriptions

    public function test_bulk_delete_skips_plans_that_have_subscriptions(): void
    {
        $admin = $this->superAdmin();
        $used = Plan::factory()->create();
        $unused = Plan::factory()->create();
        Subscription::factory()->create(['plan_id' => $used->id, 'user_id' => $admin->id]);

        Livewire::actingAs($admin)
            ->test(PlansManager::class)
            ->set('selected', [(string) $used->id, (string) $unused->id])
            ->call('bulkDelete');

        $this->assertNotNull(Plan::find($used->id));
        $this->assertSoftDeleted('plans', ['id' => $unused->id]);
    }

    public function test_plan_manager_preserves_attached_inactive_features_but_rejects_tampered_inactive_ids(): void
    {
        $admin = $this->superAdmin();
        $plan = Plan::factory()->create();
        $attachedInactive = Feature::factory()->create(['is_active' => false]);
        $tamperedInactive = Feature::factory()->create(['is_active' => false]);
        $plan->features()->attach($attachedInactive->id);

        Livewire::actingAs($admin)->test(PlansManager::class)
            ->call('edit', $plan->id)
            ->set('featureIds', [$attachedInactive->id, $tamperedInactive->id])
            ->call('save')
            ->assertHasErrors('featureIds.*');

        $this->assertDatabaseHas('feature_plan', ['plan_id' => $plan->id, 'feature_id' => $attachedInactive->id]);
        $this->assertDatabaseMissing('feature_plan', ['plan_id' => $plan->id, 'feature_id' => $tamperedInactive->id]);
    }

    public function test_admin_can_activate_extend_and_cancel_subscriptions(): void
    {
        $admin = $this->superAdmin();
        $plan = Plan::factory()->create(['duration_days' => 30, 'type' => 'monthly', 'grace_period_days' => 0]);
        $subscription = Subscription::factory()->create([
            'plan_id' => $plan->id,
            'user_id' => $admin->id,
            'status' => 'pending',
            'start_date' => null,
            'end_date' => null,
        ]);

        $component = Livewire::actingAs($admin)->test(SubscriptionsManager::class)
            ->call('activate', $subscription->id);

        $subscription->refresh();
        $this->assertSame('active', $subscription->status);
        $originalEnd = $subscription->end_date->copy();

        $component->call('openExtend', $subscription->id)
            ->set('extendDays', 15)
            ->call('applyExtension')
            ->assertHasNoErrors();

        $this->assertTrue($subscription->fresh()->end_date->equalTo($originalEnd->copy()->addDays(15)));

        $component->call('cancel', $subscription->id);
        $this->assertSame('cancelled', $subscription->fresh()->status);
    }

    public function test_bulk_subscription_delete_never_removes_active_ones(): void
    {
        $admin = $this->superAdmin();
        $active = Subscription::factory()->create(['user_id' => $admin->id, 'status' => 'active']);
        $cancelled = Subscription::factory()->create(['user_id' => $admin->id, 'status' => 'cancelled']);

        Livewire::actingAs($admin)
            ->test(SubscriptionsManager::class)
            ->set('selected', [(string) $active->id, (string) $cancelled->id])
            ->call('bulkDelete');

        $this->assertNotNull(Subscription::find($active->id));
        $this->assertSoftDeleted('subscriptions', ['id' => $cancelled->id]);
    }

    public function test_admin_overview_has_no_duplicate_statistics_tab(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('admin.index'))
            ->assertOk()
            ->assertDontSee("'statistics'", false)
            ->assertDontSee('>Statistics<', false);
    }

    // ---------------------------------------------------------------- audit fixes

    public function test_disabled_users_cannot_sign_in_on_the_web(): void
    {
        $user = User::factory()->create(['is_active' => false, 'password' => bcrypt('password')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_disabled_users_are_signed_out_of_existing_sessions_and_api(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ACCOUNT_DISABLED');
    }

    public function test_web_registration_grants_viewer_role_and_respects_setting(): void
    {
        Role::firstOrCreate(['slug' => 'user'], ['name' => 'Viewer']);

        $this->post('/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => '1',
        ])->assertRedirect();

        $this->assertTrue(User::where('email', 'new@example.com')->firstOrFail()->hasAnyRole(['user']));

        auth()->guard('web')->logout();
        SiteSetting::set('allow_registration', false, 'general', 'boolean');

        $this->get('/register')->assertForbidden();
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Another',
            'email' => 'another@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertStatus(403)->assertJsonPath('error_code', 'REGISTRATION_DISABLED');
    }

    public function test_pricing_job_cannot_be_started_on_another_tenants_boq(): void
    {
        $owner = Organisation::factory()->create();
        $project = Project::factory()->create(['organisation_id' => $owner->id]);
        $boq = Boq::factory()->create(['organisation_id' => $owner->id, 'project_id' => $project->id]);

        $attacker = User::factory()->create(['organisation_id' => Organisation::factory()->create()->id]);
        $attacker->permissions()->attach(Permission::factory()->create(['slug' => 'boq.edit', 'name' => 'boq.edit', 'module' => 'boq']));
        Entitlement::factory()->create([
            'user_id' => $attacker->id,
            'organisation_id' => $attacker->organisation_id,
            'feature_id' => Feature::factory()->create(['code' => 'boq.management'])->id,
            'status' => 'active',
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($attacker, 'sanctum')
            ->postJson(route('api.v1.boqs.pricing-jobs.store', $boq), ['location' => 'Kampala'])
            ->assertForbidden();
    }

    public function test_payment_verification_rejects_a_reused_gateway_transaction_id(): void
    {
        $user = User::factory()->create();
        $gateway = PaymentGateway::create([
            'name' => 'MTN',
            'code' => 'mtn_test',
            'driver' => 'mtn_momo',
            'is_active' => true,
            'is_default' => false,
            'is_test_mode' => true,
            'payment_timeout_seconds' => 900,
            'config' => [],
        ]);

        Transaction::create([
            'reference' => 'OLD-REF',
            'user_id' => $user->id,
            'payment_gateway_id' => $gateway->id,
            'gateway_transaction_id' => 'already-paid-id',
            'amount' => 1000,
            'currency' => 'UGX',
            'payment_method' => 'momo',
            'status' => 'successful',
            'initiated_at' => now(),
        ]);

        $pending = Transaction::create([
            'reference' => 'NEW-REF',
            'user_id' => $user->id,
            'payment_gateway_id' => $gateway->id,
            'amount' => 500000,
            'currency' => 'UGX',
            'payment_method' => 'momo',
            'status' => 'initiated',
            'initiated_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/transactions/{$pending->id}/verify", ['gateway_transaction_id' => 'already-paid-id'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'PAYMENT_ALREADY_USED');

        $this->assertSame('initiated', $pending->fresh()->status);
    }
}
