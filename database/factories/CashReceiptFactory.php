<?php

namespace Database\Factories;

use App\Models\CashReceipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashReceipt>
 */
class CashReceiptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'receipt_number' => 'PPD-'.fake()->unique()->numerify('#####'),
            'invoice_id' => null,
            'client_id' => User::factory()->customer(),
            'client_name' => fake()->name(),
            'purpose' => fake()->sentence(3),
            'received_by' => fake()->name(),
            'amount' => fake()->numberBetween(3, 20) * 100,
            'received_at' => today(),
        ];
    }
}
