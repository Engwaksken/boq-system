<?php

namespace Database\Factories;

use App\Models\Quotation;
use App\Models\QuotationItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\QuotationItem>
 */
class QuotationItemFactory extends Factory
{
    protected $model = QuotationItem::class;

    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(2, 1, 500);
        $unitPrice = $this->faker->randomFloat(2, 100, 50000);

        return [
            'quotation_id' => Quotation::factory(),
            'boq_item_id' => null,
            'sort_order' => 0,
            'product' => $this->faker->randomElement([
                'Cement 42.5N (50kg)', 'Sharp sand (per tonne)', 'Hardcore (per m3)', 'Reinforcement steel 12mm',
                'PVC water pipe 110mm', 'Emulsion paint 20ltr', 'Electrical cable 2.5mm (roll)',
            ]),
            'description' => $this->faker->sentence(),
            'unit' => $this->faker->randomElement(['NO', 'm2', 'm3', 'tonne', 'bar', 'roll', 'ltr']),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'vat_rate' => 18,
            'line_total' => round($quantity * $unitPrice, 2),
            'matched' => false,
            'matched_rate_id' => null,
            'approved' => false,
            'source_item_code' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(['approved' => true]);
    }
}