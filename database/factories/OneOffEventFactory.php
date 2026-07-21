<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\EventCategory;
use App\Models\OneOffEvent;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OneOffEvent>
 */
class OneOffEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement(['Workshop zdravých zad', 'Dýchací techniky', 'Cvičení s overballem', 'Mobilita kyčlí', 'Pánevní dno']).' '.fake()->unique()->numberBetween(1, 1000000);
        $start = fake()->numberBetween(9, 17);

        return [
            'event_category_id' => EventCategory::factory(),
            'course_id' => null,
            'instructor_id' => User::factory()->therapist(),
            'room_id' => Room::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->boolean(60) ? fake()->paragraph() : null,
            'event_date' => fake()->dateTimeBetween('-1 week', '+2 months')->format('Y-m-d'),
            'start_time' => sprintf('%02d:00', $start),
            'end_time' => sprintf('%02d:00', $start + 2),
            'capacity' => fake()->numberBetween(4, 25),
            'price' => fake()->numberBetween(200, 1500),
            'published_at' => fake()->boolean(80) ? now() : null,
        ];
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
