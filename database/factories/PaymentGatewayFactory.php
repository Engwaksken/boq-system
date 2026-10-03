<?php

namespace Database\Factories;

use App\Models\PaymentGateway;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentGateway>
 */
class PaymentGatewayFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PaymentGateway::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Card (Stripe)',
            'code' => 'gateway_'.$this->faker->unique()->bothify('??????'),
            'driver' => 'stripe',
            'description' => $this->faker->sentence(),
            'config' => ['secret_key' => 'sk_test_'.$this->faker->bothify('????????')],
            'supported_currencies' => ['USD'],
            'supported_countries' => [],
            'supported_methods' => ['card'],
            'is_active' => true,
            'is_default' => false,
            'is_test_mode' => true,
            'webhook_url' => null,
            'payment_timeout_seconds' => 900,
            'refund_settings' => [],
        ];
    }
}
