<?php

namespace Database\Factories;

use App\Enums\CourseSeriesStatus;
use App\Enums\CourseSeriesVisibility;
use App\Enums\DayOfWeek;
use App\Models\Course;
use App\Models\CourseSeries;
use App\Models\Room;
use App\Support\Lessons\ScheduleSlot;
use App\Support\Lessons\SeriesSchedule;
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
            'max_substitutions' => fake()->numberBetween(0, 5),
            'price' => fake()->numberBetween(1500, 6000),
            'status' => fake()->randomElement(CourseSeriesStatus::cases()),
            'visibility' => CourseSeriesVisibility::Public,
        ];
    }

    /**
     * A série whose recurring rozvrh is filled in, so its lessons can be
     * generated. Defaults to a single weekday at one time; pass several days for
     * a série that meets more than once a week, all sharing the given time.
     *
     * @param  array<int, DayOfWeek>  $days
     */
    public function withSchedule(array $days = [DayOfWeek::Monday], string $start = '17:00', string $end = '18:00'): static
    {
        return $this->withScheduleSlots(
            array_map(fn (DayOfWeek $day): ScheduleSlot => new ScheduleSlot($day, $start, $end), $days)
        );
    }

    /**
     * The general form: each slot carries its own day and time, for a série
     * meeting e.g. on středa at 9:00 and čtvrtek at 10:30.
     *
     * @param  array<int, ScheduleSlot>  $slots
     */
    public function withScheduleSlots(array $slots): static
    {
        return $this->state(fn (array $attributes): array => [
            'schedule' => SeriesSchedule::fromSlots($slots)->toArray(),
            'room_id' => Room::factory(),
        ]);
    }
}
