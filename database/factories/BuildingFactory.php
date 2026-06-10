<?php

namespace Database\Factories;

use App\Models\Building;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Building>
 */
class BuildingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Budova '.fake()->unique()->numberBetween(1, 999),
            'address' => fake('cs_CZ')->streetAddress().', '.fake('cs_CZ')->city(),
        ];
    }
}
