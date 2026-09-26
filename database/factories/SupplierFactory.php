<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'code' =>
                'SUP-'.strtoupper(
                    $this->faker
                        ->unique()
                        ->lexify('??????')
                ),

            'name' =>
                $this->faker
                    ->unique()
                    ->company(),

            'contact_name' =>
                $this->faker->name(),

            'phone' =>
                $this->faker->phoneNumber(),

            'email' =>
                $this->faker
                    ->unique()
                    ->safeEmail(),

            'location' =>
                $this->faker->streetAddress(),

            'region' =>
                $this->faker->randomElement([
                    'Kampala',
                    'Entebbe',
                    'Jinja',
                    'Mbarara',
                    'Gulu',
                ]),

            'country' => 'UG',

            'currency' => 'UGX',

            'materials' =>
                $this->faker->randomElements(
                    [
                        'cement',
                        'aggregates',
                        'steel',
                        'paint',
                        'tiles',
                        'timber',
                        'roofing',
                    ],
                    3
                ),

            'notes' =>
                $this->faker->sentence(),

            'preferred_language' =>
                'en',

            /*
             * rating is NOT NULL in the database.
             *
             * Never use optional() here because SQLite/MySQL
             * will reject null values.
             */
            'rating' =>
                $this->faker->randomFloat(
                    1,
                    2,
                    5
                ),

            'is_active' => true,

            'created_by' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state([
            'is_active' => false,
        ]);
    }

    public function highlyRated(): static
    {
        return $this->state([
            'rating' => 5.0,
        ]);
    }
}