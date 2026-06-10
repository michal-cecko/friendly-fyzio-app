<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\OneTimeLesson;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OneTimeLesson>
 */
class OneTimeLessonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->numberBetween(8, 18);

        return [
            'course_id' => Course::factory(),
            'instructor_id' => User::factory()->therapist(),
            'room_id' => Room::factory(),
            'lesson_date' => fake()->dateTimeBetween('-1 week', '+2 months')->format('Y-m-d'),
            'start_time' => sprintf('%02d:00', $start),
            'end_time' => sprintf('%02d:00', $start + 1),
            'capacity' => fake()->numberBetween(4, 15),
            'price' => fake()->numberBetween(200, 800),
        ];
    }
}
