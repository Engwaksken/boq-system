<?php

namespace Database\Factories;

use App\Models\Organisation;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Transaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'TXN-' . strtoupper($this->faker->unique()->bothify('??#######')),
            'idempotency_key' => $this->faker->unique()->uuid(),
            'plan_id' => Plan::factory(),
            'payment_gateway_id' => PaymentGateway::factory(),
            'product_type' => 'subscription',
            'amount' => $this->faker->randomFloat(2, 10, 500),
            'currency' => 'USD',
            'payment_method' => $this->faker->randomElement(['card', 'bank_transfer', 'wallet']),
            'gateway_transaction_id' => 'gw_' . $this->faker->unique()->bothify('????????????????'),
            'status' => 'successful',
            'initiated_at' => now(),
            'completed_at' => now(),
            'refund_status' => 'none',
            'metadata' => [],
        ];
    }

    /**
     * Configure the model factory to set non-mass-assignable fields explicitly.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Transaction $transaction) {
            if (! $transaction->user_id) {
                $transaction->user_id = User::factory()->create()->id;
            }
            if (! $transaction->organisation_id) {
                $transaction->organisation_id = $transaction->user?->organisation_id ?? Organisation::factory()->create()->id;
            }
            if (! $transaction->subscription_id) {
                $transaction->subscription_id = Subscription::factory()->create()->id;
            }
        });
    }

    /**
     * Self-paid transaction state (user pays for themselves).
     */
    public function selfPaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'payer_id' => null,
        ]);
    }

    /**
     * Proxy-paid transaction state (admin pays for another user).
     */
    public function proxyPaid(): static
    {
        return $this->state(function (array $attributes) {
            $beneficiary = User::factory()->create();
            $payer = User::factory()->create();

            return [
                'user_id' => $beneficiary->id, // backward compatibility - beneficiary
                'payer_id' => $payer->id,
                'organisation_id' => $beneficiary->organisation_id,
            ];
        });
    }

    /**
     * Proxy-paid transaction for a specific beneficiary and payer.
     */
    public function forBeneficiary(User $beneficiary, ?User $payer = null): static
    {
        return $this->state(function (array $attributes) use ($beneficiary, $payer) {
            $payerUser = $payer ?? User::factory()->create();

            return [
                'user_id' => $beneficiary->id, // backward compatibility - beneficiary
                'payer_id' => $payerUser->id,
                'organisation_id' => $beneficiary->organisation_id,
            ];
        });
    }

    /**
     * Failed transaction state.
     */
    public function failed(string $reason = 'Payment declined'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'failed_at' => now(),
            'failure_reason' => $reason,
            'completed_at' => null,
        ]);
    }

    /**
     * Pending transaction state.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'completed_at' => null,
            'failed_at' => null,
        ]);
    }

    /**
     * Refunded transaction state.
     */
    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'refunded',
            'refund_status' => 'full',
            'completed_at' => now(),
        ]);
    }

    /**
     * Partially refunded transaction state.
     */
    public function partiallyRefunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'partially_refunded',
            'refund_status' => 'partial',
            'completed_at' => now(),
        ]);
    }
}