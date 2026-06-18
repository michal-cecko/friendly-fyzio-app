<?php

namespace Database\Factories;

use App\Models\CalendarBlock;
use App\Models\TherapistProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarBlock>
 */
class CalendarBlockFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('now', '+2 weeks');

        return [
            'therapist_id' => TherapistProfile::factory(),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => (clone $start)->modify('+'.fake()->numberBetween(0, 6).' days')->format('Y-m-d'),
            'reason' => fake()->randomElement(['Dovolená', 'Nemoc', 'Školení']),
        ];
    }
}
