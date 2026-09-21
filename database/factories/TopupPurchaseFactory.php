<?php

namespace Database\Factories;

use App\Models\Topup;
use App\Models\TopupPurchase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TopupPurchase>
 */
class TopupPurchaseFactory extends Factory
{
    protected $model = TopupPurchase::class;

    public function definition(): array
    {
        return [
            'topup_id' => Topup::factory(),
            'user_id' => User::factory(),
            'organisation_id' => null,
            'subscription_id' => null,
            'transaction_id' => null,
            'status' => 'pending',
            'purchased_at' => now(),
            'activated_at' => null,
            'expires_at' => null,
            'is_permanent' => false,
            'version' => null,
            'metadata' => null,
        ];
    }
}