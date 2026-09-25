<?php

namespace Tests\Feature;

use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentNetworkValidationTest extends TestCase
{
    use RefreshDatabase;

    private function createPayableSubscription(User $user): Subscription
    {
        $plan = Plan::factory()->create();

        PaymentGateway::create([
            'code' => 'bank_transfer',
            'driver' => 'bank_transfer',
            'name' => 'Bank Transfer',
            'is_active' => true,
            'supported_currencies' => [],
            'supported_methods' => [],
        ]);

        return Subscription::factory()->create([
            'plan_id' => $plan->id,
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'status' => 'pending',
        ]);
    }

    public function test_network_rejects_payment_method_identifier(): void
    {
        $user = User::factory()->create();
        $subscription = $this->createPayableSubscription($user);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/subscriptions/{$subscription->id}/payments", [
                'gateway_code' => 'bank_transfer',
                'network' => 'mobile_money',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('network');
    }

    public function test_network_accepts_mobile_network_identifier(): void
    {
        $user = User::factory()->create();
        $subscription = $this->createPayableSubscription($user);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/subscriptions/{$subscription->id}/payments", [
                'gateway_code' => 'bank_transfer',
                'network' => 'MTN',
            ]);

        $response->assertStatus(201)
            ->assertJsonMissingValidationErrors('network')
            ->assertJson(['success' => true]);
    }

    public function test_network_rejects_payment_method_identifier_case_insensitively(): void
    {
        $user = User::factory()->create();
        $subscription = $this->createPayableSubscription($user);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/subscriptions/{$subscription->id}/payments", [
                'gateway_code' => 'bank_transfer',
                'network' => 'MOBILE_MONEY',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('network');
    }
}