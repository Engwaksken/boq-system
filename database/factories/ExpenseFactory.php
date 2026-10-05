<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\Organisation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Expense> */
class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'project_id' => Project::factory(),
            'creator_user_id' => User::factory(),
            'purchaser_user_id' => null,
            'purchase_date' => fake()->date(),
            'supplier' => fake()->company(),
            'description' => fake()->sentence(),
            'quantity' => 1,
            'unit' => 'item',
            'rate' => 100,
            'total' => 100,
            'currency' => 'UGX',
            'payment_method' => null,
            'is_planned' => true,
            'explanation' => null,
        ];
    }
}
