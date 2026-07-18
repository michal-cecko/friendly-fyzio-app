<?php

namespace Database\Factories;

use App\Enums\CourseSeriesStatus;
use App\Enums\CourseSeriesVisibility;
use App\Models\Course;
use App\Models\CourseSeries;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseSeries>
 */
class CourseSeriesFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 month', '+1 month');

        return [
            'course_id' => Course::factory(),
            'name' => 'Série '.fake()->randomElement(['jaro', 'léto', 'podzim', 'zima']).' '.fake()->year(),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => fake()->dateTimeBetween($start, '+4 months')->format('Y-m-d'),
            'capacity' => fake()->numberBetween(6, 20),
            'price' => fake()->numberBetween(1500, 6000),
            'status' => fake()->randomElement(CourseSeriesStatus::cases()),
            'visibility' => CourseSeriesVisibility::Public,
        ];
    }
}
