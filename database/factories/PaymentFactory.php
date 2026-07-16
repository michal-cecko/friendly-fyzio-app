<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'amount' => fake()->numberBetween(1, 20) * 100,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Unpaid,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function cash(): static
    {
        return $this->state(fn (): array => [
            'method' => PaymentMethod::Cash,
        ]);
    }
}
