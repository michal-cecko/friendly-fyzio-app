<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'building_id' => Building::factory(),
            'name' => fake()->randomElement(['Ordinace', 'Tělocvična', 'Masážní místnost', 'Rehabilitace', 'Vyšetřovna']).' '.fake()->numberBetween(1, 9),
        ];
    }
}
