<?php

namespace Database\Factories;

use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\LessonAttendance;
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

        return [
            'enrollment_id' => CourseEnrollment::factory(),
            'lesson_id' => CourseLesson::factory(),
            'attended' => ! $cancelled && fake()->boolean(80),
            'cancelled_at' => $cancelled ? now() : null,
            'token_generated' => fake()->boolean(20),
        ];
    }
}
