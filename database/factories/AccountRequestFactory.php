<?php

namespace Database\Factories;

use App\AccountRequestStatus;
use App\Models\AccountRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountRequest>
 */
class AccountRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'display_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'status' => AccountRequestStatus::PendingEmailConfirmation,
            'email_confirmed_at' => null,
            'submitted_at' => now(),
            'expires_at' => now()->addDays(7),
        ];
    }
}
