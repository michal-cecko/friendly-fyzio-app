<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Lesson;
use App\Models\LessonBooking;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonBooking>
 */
class LessonBookingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $paymentStatus = fake()->randomElement(PaymentStatus::payableCases());

        return [
            'client_id' => User::factory()->customer(),
            'lesson_id' => Lesson::factory()->standalone(),
            'status' => fake()->randomElement(['confirmed', 'pending', 'cancelled']),
            'payment_status' => $paymentStatus,
            'paid_at' => $paymentStatus === PaymentStatus::Paid ? now() : null,
        ];
    }
}
