<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'start_date' => now(),
            'end_date' => now()->addDays(30),
            'renewal_date' => now()->addDays(30),
            'auto_renewal' => true,
        ];
    }

    /**
     * Configure the model factory to set non-mass-assignable fields explicitly.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Subscription $subscription) {
            if (! $subscription->user_id) {
                $subscription->user_id = User::factory()->create()->id;
            }
            if (! $subscription->organisation_id) {
                $subscription->organisation_id = $subscription->user?->organisation_id;
            }
            if (! $subscription->status) {
                $subscription->status = 'active';
            }
            if (! $subscription->access_type) {
                $subscription->access_type = 'monthly';
            }
            if (! $subscription->payment_status) {
                $subscription->payment_status = 'paid';
            }
        });
    }

    /**
     * Self-subscription state (user pays for themselves).
     */
    public function selfSubscription(): static
    {
        return $this->state(fn (array $attributes) => [
            'payer_id' => null,
            'beneficiary_id' => null,
        ]);
    }

    /**
     * Proxy subscription state (admin pays for another user).
     */
    public function proxySubscription(): static
    {
        return $this->state(function (array $attributes) {
            $beneficiary = User::factory()->create();
            $payer = User::factory()->create();

            return [
                'user_id' => $beneficiary->id, // backward compatibility
                'beneficiary_id' => $beneficiary->id,
                'payer_id' => $payer->id,
                'organisation_id' => $beneficiary->organisation_id,
            ];
        });
    }

    /**
     * Proxy subscription where the beneficiary is explicitly set.
     */
    public function forBeneficiary(User $beneficiary, ?User $payer = null): static
    {
        return $this->state(function (array $attributes) use ($beneficiary, $payer) {
            $payerUser = $payer ?? User::factory()->create();

            return [
                'user_id' => $beneficiary->id, // backward compatibility
                'beneficiary_id' => $beneficiary->id,
                'payer_id' => $payerUser->id,
                'organisation_id' => $beneficiary->organisation_id,
            ];
        });
    }
}
