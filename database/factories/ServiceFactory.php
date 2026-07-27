<?php

namespace Database\Factories;

use App\Enums\ServiceVisibility;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement([
            'Vstupní vyšetření', 'Klasická masáž', 'Lymfatická drenáž', 'Léčebná tělesná výchova',
            'Suché jehličkování', 'Kineziotaping', 'Měkké techniky', 'Mobilizace páteře',
            'Sportovní masáž', 'Terapie rázovou vlnou',
        ]);

        return [
            'category_id' => ServiceCategory::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'duration_minutes' => fake()->numberBetween(1, 6) * 15,
            'price' => fake()->numberBetween(2, 30) * 100,
            'visibility' => fake()->randomElement(ServiceVisibility::cases()),
            'published_at' => fake()->boolean(80) ? now() : null,
        ];
    }
}
