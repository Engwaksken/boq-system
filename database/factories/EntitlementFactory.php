<?php

namespace Database\Factories;

use App\Models\Entitlement;
use App\Models\Feature;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Entitlement>
 */
class EntitlementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subscription_id' => Subscription::factory(),
            'feature_id' => Feature::factory(),
            'plan_id' => Plan::factory(),
            'source' => 'plan',
            'status' => 'active',
            'granted_at' => now(),
            'expires_at' => now()->addDays(30),
            'is_permanent' => false,
        ];
    }
}
