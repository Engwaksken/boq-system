<?php

namespace Database\Factories;

use App\Models\Invitation;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Invitation> */
class InvitationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role_id' => null,
            'inviter_user_id' => User::factory(),
            'token_hash' => Invitation::hashCode((string) fake()->unique()->randomNumber(5, true)),
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
            'revoked_at' => null,
            'revoked_by_user_id' => null,
            'consumed_at' => null,
            'attempt_count' => 0,
            'last_attempt_at' => null,
        ];
    }
}
