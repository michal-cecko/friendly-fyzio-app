<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseSeries;
use App\Models\EventCategory;
use App\Models\Lesson;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A lesson of a course série by default — that is the common case, and what the
 * roster, substitute and attendance suites expect. {@see standalone()} turns it
 * into a workshop / jednorázová lekce with its own name, capacity and price, and
 * {@see releasable()} prices a série lesson so it can go on public sale.
 *
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->numberBetween(8, 18);

        return [
            'series_id' => CourseSeries::factory(),
            'instructor_id' => User::factory()->lecturer(),
            'room_id' => Room::factory(),
            'lesson_date' => fake()->dateTimeBetween('-1 month', '+2 months')->format('Y-m-d'),
            'start_time' => sprintf('%02d:00', $start),
            'end_time' => sprintf('%02d:00', $start + 1),
        ];
    }

    /**
     * A standalone offer: no série, so it carries its own name, capacity, price
     * and the category that forms its public URL.
     */
    public function standalone(): static
    {
        return $this->state(function (): array {
            $name = fake()->randomElement([
                'Workshop zdravých zad', 'Dýchací techniky', 'Cvičení s overballem',
                'Mobilita kyčlí', 'Pánevní dno',
            ]).' '.fake()->unique()->numberBetween(1, 1000000);
            $start = fake()->numberBetween(9, 17);

            return [
                'series_id' => null,
                'event_category_id' => EventCategory::factory(),
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => fake()->boolean(60) ? fake()->paragraph() : null,
                'start_time' => sprintf('%02d:00', $start),
                'end_time' => sprintf('%02d:00', $start + 2),
                'capacity' => fake()->numberBetween(4, 25),
                'price' => fake()->numberBetween(200, 1500),
                'published_at' => fake()->boolean(80) ? now() : null,
            ];
        });
    }

    /**
     * A lesson of a série whose course sells single seats — the precondition for
     * a free place ever going on public sale.
     */
    public function releasable(int $dropInPrice = 260): static
    {
        return $this->state(fn (): array => [
            'series_id' => CourseSeries::factory()->for(
                Course::factory()->state(['drop_in_price' => $dropInPrice]),
            ),
        ]);
    }

    public function forCategory(EventCategory $category): static
    {
        return $this->state(['event_category_id' => $category->id]);
    }

    public function withCourse(?Course $course = null): static
    {
        return $this->state(['course_id' => $course?->id ?? Course::factory()]);
    }

    public function published(): static
    {
        return $this->state(['published_at' => now()]);
    }

    public function unpublished(): static
    {
        return $this->state(['published_at' => null]);
    }
}
