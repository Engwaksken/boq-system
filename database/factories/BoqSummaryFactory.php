<?php

namespace Database\Factories;

use App\Models\Boq;
use App\Models\BoqSummary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoqSummary>
 */
class BoqSummaryFactory extends Factory
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
            'summary_type' => 'grand',
            'subtotal' => 0,
            'vat' => 0,
            'contingency' => 0,
            'grand_total' => 0,
        ];
    }
}
