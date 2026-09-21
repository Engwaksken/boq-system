<?php

namespace Database\Factories;

use App\Models\Topup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Topup>
 */
class TopupFactory extends Factory
{
    protected $model = Topup::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(3, true),
            'code' => $this->faker->unique()->slug(3),
            'description' => $this->faker->sentence(),
            'type' => 'bundle',
            'price' => 5000,
            'currency' => 'UGX',
            'duration_days' => 30,
            'is_permanent' => false,
            'release_version' => null,
            'included_features' => [],
            'usage_credits' => [],
            'applicable_plans' => [],
            'purchase_limit' => null,
            'requires_confirmation' => true,
            'is_active' => true,
            'is_archived' => false,
            'display_order' => 0,
        ];
    }
}