<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Enums\LessonExcuseReason;
use App\Models\CourseEnrollment;
use App\Models\Lesson;
use App\Models\LessonAttendance;
use App\Models\LessonBooking;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonAttendance>
 */
class LessonAttendanceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cancelled = fake()->boolean(15);

        // client_id is filled in by the model from the enrollment it names. The
        // enrollment's série and the lesson's are unrelated here, so the default
        // row reads as a substitute guest — see substituteGuest() to say so.
        return [
            'enrollment_id' => CourseEnrollment::factory(),
            'lesson_id' => Lesson::factory(),
            'attended' => ! $cancelled,
            'cancelled_at' => $cancelled ? now() : null,
            'token_generated' => fake()->boolean(20),
        ];
    }

    /**
     * Somebody who bought this single lesson rather than the whole série. The
     * booking is confirmed unless one is handed in: a seat whose purchase was
     * cancelled reads as a cancelled sign-up everywhere, which is a different
     * row than the one this state is asked for.
     */
    public function dropIn(?LessonBooking $booking = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'enrollment_id' => null,
            'booking_id' => $booking?->getKey() ?? LessonBooking::factory()->state(['status' => BookingStatus::Confirmed]),
            'lesson_id' => $booking?->lesson_id ?? $attributes['lesson_id'],
            'client_id' => $booking?->client_id,
        ]);
    }

    /**
     * A client sitting in on somebody else's run: enrolled in one série, seated
     * in a lesson of another.
     */
    public function substituteGuest(Lesson $lesson, CourseEnrollment $enrollment): static
    {
        return $this->state(fn (): array => [
            'lesson_id' => $lesson->getKey(),
            'enrollment_id' => $enrollment->getKey(),
            'client_id' => $enrollment->client_id,
        ]);
    }

    public function attended(): static
    {
        return $this->state(fn (): array => [
            'attended' => true,
            'cancelled_at' => null,
            'token_generated' => false,
        ]);
    }

    public function excused(?LessonExcuseReason $reason = null, bool $withToken = false): static
    {
        return $this->state(fn (): array => [
            'attended' => false,
            'cancelled_at' => now(),
            'excuse_reason' => $reason ?? LessonExcuseReason::Illness,
            'token_generated' => $withToken,
        ]);
    }
}
