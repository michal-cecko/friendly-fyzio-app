<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\OneTimeLesson;
use App\Models\OneTimeLessonBooking;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OneTimeLessonBooking>
 */
class OneTimeLessonBookingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $paymentStatus = fake()->randomElement(PaymentStatus::cases());

        return [
            'client_id' => User::factory()->customer(),
            'lesson_id' => OneTimeLesson::factory(),
            'status' => fake()->randomElement(['confirmed', 'pending', 'cancelled']),
            'payment_status' => $paymentStatus,
            'paid_at' => $paymentStatus === PaymentStatus::Paid ? now() : null,
        ];
    }
}
