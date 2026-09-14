<?php

namespace Database\Factories;

use App\Models\BoqItem;
use App\Models\BoqItemTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoqItemTranslation>
 */
class BoqItemTranslationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'boq_item_id' => BoqItem::factory(),
            'locale' => 'fr',
            'translated_description' => fake()->sentence(),
            'status' => 'pending_review',
        ];
    }
}
