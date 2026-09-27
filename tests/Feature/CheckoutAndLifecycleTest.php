<?php

namespace Tests\Feature;

use App\Livewire\Checkout;
use App\Models\Entitlement;
use App\Models\Feature;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\Format;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class CheckoutAndLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function stripeGateway(): PaymentGateway
    {
        return PaymentGateway::create([
            'name' => 'Card (Stripe)',
            'code' => 'stripe_test',
            'driver' => 'stripe',
            'is_active' => true,
            'is_default' => true,
            'is_test_mode' => true,
            'payment_timeout_seconds' => 900,
            'supported_currencies' => ['USD', 'UGX'],
            'config' => ['secret_key' => 'sk_test_123'],
        ]);
    }

    private function pendingSubscription(User $user, array $planAttributes = []): Subscription
    {
        $plan = Plan::factory()->create($planAttributes + ['price' => 25, 'currency' => 'USD', 'duration_days' => 30, 'type' => 'monthly']);

        return Subscription::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'plan_id' => $plan->id,
            'status' => 'pending',
            'payment_status' => 'pending',
            'start_date' => null,
            'end_date' => null,
        ]);
    }

    // ---------------------------------------------------------------- checkout

    public function test_card_checkout_redirects_to_stripe_and_activates_on_return(): void
    {
        $this->stripeGateway();
        $user = User::factory()->create();
        $subscription = $this->pendingSubscription($user);

        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/pay/cs_test_1']),
            'api.stripe.com/v1/checkout/sessions/cs_test_1' => Http::response([
                'id' => 'cs_test_1',
                'payment_status' => 'paid',
                'status' => 'complete',
                'amount_total' => 2500,
                'currency' => 'usd',
                'client_reference_id' => null, // filled below via the stored reference
            ]),
        ]);

        Livewire::actingAs($user)
            ->test(Checkout::class, ['type' => 'plan', 'id' => $subscription->id])
            ->assertSee('Card (Stripe)')
            ->set('gatewayCode', 'stripe_test')
            ->call('pay')
            ->assertRedirect('https://checkout.stripe.com/pay/cs_test_1');

        $transaction = Transaction::where('subscription_id', $subscription->id)->sole();
        $this->assertSame('cs_test_1', $transaction->gateway_transaction_id);
        $this->assertStringContainsString('transaction='.$transaction->id, $transaction->metadata['return_url']);

        // The customer returns from Stripe: the page confirms and activates.
        Http::fake([
            'api.stripe.com/v1/checkout/sessions/cs_test_1' => Http::response([
                'id' => 'cs_test_1', 'payment_status' => 'paid', 'status' => 'complete',
                'amount_total' => 2500, 'currency' => 'usd', 'client_reference_id' => $transaction->reference,
            ]),
        ]);

        Livewire::actingAs($user)
            ->withQueryParams(['transaction' => $transaction->id])
            ->test(Checkout::class, ['type' => 'plan', 'id' => $subscription->id])
            ->assertRedirect(route('subscriptions.index'));

        $this->assertSame('successful', $transaction->fresh()->status);
        $this->assertSame('active', $subscription->fresh()->status);
    }

    public function test_stripe_amount_mismatch_is_not_accepted(): void
    {
        $this->stripeGateway();
        $user = User::factory()->create();
        $subscription = $this->pendingSubscription($user);

        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_2', 'url' => 'https://checkout.stripe.com/pay/cs_test_2']),
        ]);

        Livewire::actingAs($user)
            ->test(Checkout::class, ['type' => 'plan', 'id' => $subscription->id])
            ->set('gatewayCode', 'stripe_test')
            ->call('pay');

        $transaction = Transaction::where('subscription_id', $subscription->id)->sole();

        Http::fake([
            'api.stripe.com/v1/checkout/sessions/cs_test_2' => Http::response([
                'id' => 'cs_test_2', 'payment_status' => 'paid', 'status' => 'complete',
                'amount_total' => 100, 'currency' => 'usd', 'client_reference_id' => $transaction->reference,
            ]),
        ]);

        app(\App\Services\Payments\CheckoutService::class)->refresh($transaction);

        $this->assertSame('failed', $transaction->fresh()->status);
        $this->assertSame('pending', $subscription->fresh()->status);
    }

    public function test_users_cannot_open_checkout_for_someone_elses_subscription(): void
    {
        $subscription = $this->pendingSubscription(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->get(route('checkout', ['type' => 'plan', 'id' => $subscription->id]))
            ->assertForbidden();
    }

    public function test_choosing_a_paid_plan_goes_to_checkout(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['price' => 10, 'is_active' => true, 'is_archived' => false]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Subscriptions\Index::class)
            ->call('confirmSubscribe', $plan->id)
            ->call('performAction')
            ->assertRedirect(route('checkout', ['type' => 'plan', 'id' => Subscription::where('user_id', $user->id)->sole()->id]));
    }

    // ---------------------------------------------------------------- formats

    public function test_money_and_dates_follow_user_preferences(): void
    {
        $user = User::factory()->create([
            'timezone' => 'UTC',
            'display_preferences' => ['number_format' => '1.234,56', 'date_format' => 'd/m/Y'],
        ]);
        $this->actingAs($user);

        $this->assertSame('EUR 1.234.567,89', Format::money(1234567.891, 'EUR'));
        $this->assertSame('UGX 1.500', Format::money(1500, 'UGX')); // zero-decimal currency
        $this->assertSame('31/12/2026', Format::date('2026-12-31 10:00:00'));
        $this->assertNull(Format::date(null));
    }

    // ---------------------------------------------------------------- lifecycle

    public function test_subscriptions_enter_grace_period_then_expire(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['grace_period_days' => 3]);
        $inGrace = Subscription::factory()->create([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'active',
            'end_date' => now()->subDay(), 'grace_period_end_date' => now()->addDays(2),
        ]);
        $pastGrace = Subscription::factory()->create([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'grace_period',
            'end_date' => now()->subDays(5), 'grace_period_end_date' => now()->subDay(),
        ]);

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $this->assertSame('grace_period', $inGrace->fresh()->status);
        $this->assertSame('expired', $pastGrace->fresh()->status);
        $this->assertSame(2, UserNotification::where('user_id', $user->id)->count());
    }

    public function test_entitlements_last_through_the_grace_period(): void
    {
        $plan = Plan::factory()->create(['grace_period_days' => 5, 'duration_days' => 30, 'type' => 'monthly']);
        $feature = Feature::factory()->create(['code' => 'boq.management']);
        $plan->features()->attach($feature->id);
        $subscription = Subscription::factory()->create(['plan_id' => $plan->id, 'status' => 'pending', 'start_date' => now()]);

        app(\App\Services\SubscriptionService::class)->activate($subscription);

        $entitlement = Entitlement::where('subscription_id', $subscription->id)->sole();
        $this->assertTrue($entitlement->expires_at->equalTo($subscription->fresh()->grace_period_end_date));
    }

    public function test_renewal_reminders_are_sent_once(): void
    {
        $subscription = Subscription::factory()->create(['status' => 'active', 'end_date' => now()->addDays(7)->setTime(12, 0)]);

        $this->artisan('subscriptions:remind')->assertSuccessful();
        $this->artisan('subscriptions:remind')->assertSuccessful();

        $this->assertSame(1, UserNotification::where('user_id', $subscription->user_id)->count());
    }

    // ---------------------------------------------------------------- ops

    public function test_maintenance_mode_blocks_users_but_not_super_admins(): void
    {
        SiteSetting::set('maintenance_mode', true, 'general', 'boolean');

        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertStatus(503)->assertSee('right back');
        $this->getJson('/api/v1/plans')->assertStatus(503)->assertJsonPath('error_code', 'MAINTENANCE_MODE');

        auth()->guard('web')->logout();
        $this->get('/login')->assertOk();

        $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $admin = User::factory()->create();
        $admin->roles()->attach($role);
        $this->actingAs($admin)->get(route('dashboard'))->assertOk();
    }

    public function test_health_endpoint_reports_checks(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database.status', 'ok')
            ->assertJsonPath('checks.storage.status', 'ok');
    }
}
