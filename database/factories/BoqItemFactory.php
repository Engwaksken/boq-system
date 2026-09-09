<?php

namespace Database\Factories;

use App\Models\Boq;
use App\Models\BoqItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoqItem>
 */
class BoqItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'boq_id' => Boq::factory(),
            'item_code' => fake()->unique()->bothify('ITEM####'),
            'description' => fake()->sentence(),
            'original_language' => 'en',
            'unit' => 'NR',
            'quantity' => fake()->randomFloat(2, 1, 100),
            'original_rate' => fake()->randomFloat(2, 1000, 100000),
            'approved_rate' => fake()->randomFloat(2, 1000, 100000),
            'amount' => 0,
            'currency' => 'UGX',
            'status' => 'pending',
        ];
    }
}
