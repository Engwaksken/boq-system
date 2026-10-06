<?php

namespace Database\Factories;

use App\Models\HardwarePrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HardwarePrice>
 */
class HardwarePriceFactory extends Factory
{
    protected $model = HardwarePrice::class;

    public function definition(): array
    {
        return [
            'organisation_id' => null,
            'item_name' => fake()->words(3, true),
            'category' => fake()->word(),
            'price_type' => HardwarePrice::TYPE_HARDWARE,
            'unit' => fake()->randomElement(['each', 'kg', 'm', 'm2', 'm3']),
            'price' => fake()->randomFloat(2, 100, 100000),
            'currency' => 'UGX',
            'supplier' => fake()->company(),
            'fetched_at' => now(),
            'is_active' => true,
        ];
    }
}
