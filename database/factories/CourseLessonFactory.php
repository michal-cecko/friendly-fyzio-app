<?php

namespace Database\Factories;

use App\Models\CourseLesson;
use App\Models\CourseSeries;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseLesson>
 */
class CourseLessonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->numberBetween(8, 18);

        return [
            'series_id' => CourseSeries::factory(),
            'instructor_id' => User::factory()->therapist(),
            'room_id' => Room::factory(),
            'lesson_date' => fake()->dateTimeBetween('-1 month', '+2 months')->format('Y-m-d'),
            'start_time' => sprintf('%02d:00', $start),
            'end_time' => sprintf('%02d:00', $start + 1),
        ];
    }
}
