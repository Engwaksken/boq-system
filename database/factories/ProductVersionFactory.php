<?php

namespace Database\Factories;

use App\Models\ProductVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductVersion>
 */
class ProductVersionFactory extends Factory
{
    protected $model = ProductVersion::class;

    public function definition(): array
    {
        return [
            'version_number' => '4.0',
            'name' => $this->faker->sentence(3),
            'release_notes' => $this->faker->paragraph(),
            'release_date' => now()->toDateString(),
            'classification' => 'major',
            'included_features' => [],
            'requires_topup' => false,
            'eligible_plans' => [],
            'minimum_supported_version' => null,
            'is_active' => true,
        ];
    }
}