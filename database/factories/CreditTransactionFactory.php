<?php

namespace Database\Factories;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditTransaction>
 */
class CreditTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => User::factory()->customer(),
            'amount' => fake()->numberBetween(100, 2000),
            'type' => CreditTransactionType::TopUp,
            'description' => fake()->sentence(3),
            'expires_at' => null,
        ];
    }
}
