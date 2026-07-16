<?php

namespace Database\Factories;

use App\Models\Room;
use App\Models\TherapistProfile;
use App\Models\TherapistWorkBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TherapistWorkBlock>
 */
class TherapistWorkBlockFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->numberBetween(7, 14);

        return [
            'therapist_id' => TherapistProfile::factory(),
            'room_id' => Room::factory(),
            'series_id' => null,
            'work_date' => fake()->dateTimeBetween('now', '+2 weeks')->format('Y-m-d'),
            'start_time' => sprintf('%02d:00', $start),
            'end_time' => sprintf('%02d:00', $start + fake()->numberBetween(2, 5)),
            'note' => null,
        ];
    }
}
