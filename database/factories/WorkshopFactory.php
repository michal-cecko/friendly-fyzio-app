<?php

namespace Database\Factories;

use App\Models\Room;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Workshop>
 */
class WorkshopFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement(['Workshop zdravých zad', 'Dýchací techniky', 'Cvičení s overballem', 'Mobilita kyčlí', 'Pánevní dno']).' '.fake()->unique()->numberBetween(1, 1000000);
        $start = fake()->numberBetween(9, 17);

        return [
            'instructor_id' => User::factory()->therapist(),
            'room_id' => Room::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->boolean(60) ? fake()->paragraph() : null,
            'workshop_date' => fake()->dateTimeBetween('-1 week', '+2 months')->format('Y-m-d'),
            'start_time' => sprintf('%02d:00', $start),
            'end_time' => sprintf('%02d:00', $start + 2),
            'capacity' => fake()->numberBetween(8, 25),
            'price' => fake()->numberBetween(300, 1500),
            'published_at' => fake()->boolean(80) ? now() : null,
        ];
    }
}
