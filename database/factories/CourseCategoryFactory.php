<?php

namespace Database\Factories;

use App\Models\CourseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CourseCategory>
 */
class CourseCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement(['Pilates', 'Jóga', 'Cvičení pro seniory', 'Těhotenské cvičení', 'Rehabilitace zad', 'Dětská gymnastika']).' '.fake()->unique()->numberBetween(1, 1000000);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->boolean(60) ? fake()->sentence(10) : null,
            'published_at' => fake()->boolean(80) ? now() : null,
            'display_order' => fake()->numberBetween(0, 20),
        ];
    }
}
