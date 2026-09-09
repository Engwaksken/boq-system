<?php

namespace Database\Factories;

use App\Models\Organisation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'code' => fake()->unique()->bothify('PRJ####'),
            'client' => fake()->company(),
            'country' => 'UG',
            'district' => fake()->city(),
            'project_type' => 'building_construction',
            'currency' => 'UGX',
            'original_language' => 'en',
            'report_language' => 'en',
            'status' => 'draft',
        ];
    }
}
