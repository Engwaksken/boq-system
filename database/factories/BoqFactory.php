<?php

namespace Database\Factories;

use App\Models\Boq;
use App\Models\Organisation;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Boq>
 */
class BoqFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'organisation_id' => Organisation::factory(),
            'name' => fake()->words(2, true),
            'code' => fake()->unique()->bothify('BOQ####'),
            'original_language' => 'en',
            'currency' => 'UGX',
            'status' => 'draft',
            'version' => '1.0',
        ];
    }
}
