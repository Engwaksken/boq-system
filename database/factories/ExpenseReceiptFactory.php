<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseReceipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExpenseReceipt> */
class ExpenseReceiptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'expense_id' => Expense::factory(),
            'uploaded_by_user_id' => User::factory(),
            'original_filename' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(1024, 10485760),
            'storage_path' => 'expense-receipts/'.fake()->uuid().'.pdf',
            'storage_disk' => 'private',
        ];
    }
}
