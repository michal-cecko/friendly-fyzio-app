<?php

namespace Database\Factories;

use App\Models\EventCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EventCategory>
 */
class EventCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement(['Workshopy', 'Jednorázové lekce', 'Přednášky', 'Semináře']).' '.fake()->unique()->numberBetween(1, 1000000);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->boolean(60) ? fake()->sentence(10) : null,
            'display_order' => fake()->numberBetween(0, 20),
            'published_at' => now(),
        ];
    }

    public function unpublished(): static
    {
        return $this->state(['published_at' => null]);
    }
}
