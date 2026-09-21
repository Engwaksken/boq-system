<?php

namespace Database\Factories;

use App\Models\Quotation;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Quotation>
 */
class QuotationFactory extends Factory
{
    protected $model = Quotation::class;

    public function definition(): array
    {
        $taxRate = 18;

        return [
            'quote_number' => 'QTN-'.$this->faker->unique()->numberBetween(100000, 999999),
            'supplier_id' => Supplier::factory(),
            'project_id' => null,
            'boq_id' => null,
            'status' => 'received',
            'quotation_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'currency' => 'UGX',
            'exchange_rate' => null,
            'exchange_rate_source' => null,
            'tax_rate' => $taxRate,
            'discount_amount' => 0,
            'subtotal' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'source' => 'manual',
            'source_file_name' => null,
            'source_language' => 'en',
            'notes' => null,
            'created_by' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(['status' => 'accepted']);
    }
}