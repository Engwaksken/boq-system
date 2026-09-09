<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'code' => fake()->unique()->slug(2),
            'description' => fake()->sentence(),
            'type' => 'monthly',
            'duration_days' => 30,
            'price' => 50000,
            'currency' => 'UGX',
            'is_active' => true,
            'is_archived' => false,
            'has_trial' => false,
            'trial_days' => 0,
            'max_projects' => 10,
            'max_boqs' => 20,
            'max_ai_credits' => 200,
            'max_ocr_pages' => 200,
            'max_translations' => 1000,
            'feature_update_eligible' => true,
            'auto_renewal' => true,
            'grace_period_days' => 7,
            'display_order' => 1,
        ];
    }
}
