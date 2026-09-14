<?php

namespace Database\Factories;

use App\Models\Boq;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Facility>
 */
class FacilityFactory extends Factory
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
            'name' => fake()->words(3, true),
            'display_order' => 0,
        ];
    }
}
