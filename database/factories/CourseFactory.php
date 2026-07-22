<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement(['Zdravá záda', 'Pilates pro začátečníky', 'Jóga pro pokročilé', 'Cvičení v těhotenství', 'Senior fit']).' '.fake()->unique()->numberBetween(1, 1000000);

        return [
            'category_id' => CourseCategory::factory(),
            'instructor_id' => User::factory()->lecturer(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->boolean(60) ? fake()->paragraph() : null,
            'max_substitutions' => fake()->numberBetween(0, 5),
            'early_cancel_hours' => fake()->randomElement([12, 24, 48]),
            'published_at' => fake()->boolean(80) ? now() : null,
        ];
    }
}
