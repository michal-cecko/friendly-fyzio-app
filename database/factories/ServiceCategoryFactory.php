<?php

namespace Database\Factories;

use App\Enums\ServiceType;
use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ServiceCategory>
 */
class ServiceCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement(['Fyzioterapie', 'Masáže', 'Cvičení', 'Diagnostika', 'Rehabilitace', 'Lymfologie']);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'type' => fake()->randomElement(ServiceType::cases()),
            'published_at' => now(),
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (): array => ['published_at' => null]);
    }
}
