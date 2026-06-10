<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkshopRegistration>
 */
class WorkshopRegistrationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $paymentStatus = fake()->randomElement(PaymentStatus::cases());

        return [
            'client_id' => User::factory()->customer(),
            'workshop_id' => Workshop::factory(),
            'status' => fake()->randomElement(['confirmed', 'pending', 'cancelled']),
            'payment_status' => $paymentStatus,
            'paid_at' => $paymentStatus === PaymentStatus::Paid ? now() : null,
        ];
    }
}
