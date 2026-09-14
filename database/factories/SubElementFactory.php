<?php

namespace Database\Factories;

use App\Models\Element;
use App\Models\SubElement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubElement>
 */
class SubElementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'element_id' => Element::factory(),
            'name' => fake()->words(3, true),
            'display_order' => 0,
        ];
    }
}
