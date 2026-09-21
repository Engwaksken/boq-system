<?php

namespace Database\Factories;

use App\Models\Rate;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rate>
 */
class RateFactory extends Factory
{
    protected $model = Rate::class;

    public function definition(): array
    {
        return [
            'code' => 'RATE-'.strtoupper($this->faker->unique()->lexify('????-####')),
            'item' => $this->faker->randomElement([
                'Cement 42.5N (50kg)', 'Sharp sand (per tonne)', 'Hardcore (per m3)', 'Reinforcement steel 12mm (per bar)',
                'Class 1 bricks (per 1000)', 'Bitumen primer (per drum)', 'Painting - emulsion (per m2)',
            ]),
            'description' => $this->faker->sentence(),
            'category' => $this->faker->randomElement(['materials', 'labour', 'plant', 'transport']),
            'unit' => $this->faker->randomElement(['NO', 'm2', 'm3', 'tonne', 'bar', 'kg', 'ltr']),
            'rate' => $this->faker->randomFloat(0, 500, 500000),
            'currency' => 'UGX',
            'region' => $this->faker->randomElement(['Kampala', 'Entebbe', 'Jinja', 'Mbarara', 'Gulu', null]),
            'country' => 'UG',
            'supplier_id' => Supplier::query()->inRandomOrder()->value('id'),
            'source_type' => 'manual',
            'source_reference' => null,
            'effective_from' => now()->subDays(30)->toDateString(),
            'effective_until' => null,
            'verification_status' => 'approved',
            'verified_by' => null,
            'verified_at' => now()->subDays(20),
            'review_required_at' => now()->addDays(90)->toDateString(),
            'is_active' => true,
            'created_by' => null,
            'original_language' => 'en',
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'verification_status' => 'pending',
            'verified_by' => null,
            'verified_at' => null,
            'is_active' => false,
        ]);
    }

    public function rejected(): static
    {
        return $this->state([
            'verification_status' => 'rejected',
            'is_active' => false,
        ]);
    }
}