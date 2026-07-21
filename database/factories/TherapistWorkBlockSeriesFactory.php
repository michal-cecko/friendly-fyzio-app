<?php

namespace Database\Factories;

use App\Enums\DayOfWeek;
use App\Enums\WeekType;
use App\Models\Room;
use App\Models\StaffProfile;
use App\Models\TherapistWorkBlockSeries;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TherapistWorkBlockSeries>
 */
class TherapistWorkBlockSeriesFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->numberBetween(7, 14);
        $startsOn = now()->startOfDay();

        return [
            'therapist_id' => StaffProfile::factory(),
            'room_id' => Room::factory(),
            'day_of_week' => fake()->randomElement(DayOfWeek::cases()),
            'week_type' => WeekType::All,
            'start_time' => sprintf('%02d:00', $start),
            'end_time' => sprintf('%02d:00', $start + fake()->numberBetween(2, 5)),
            'starts_on' => $startsOn->toDateString(),
            'ends_on' => null,
            'generated_until' => $startsOn->copy()->subDay()->toDateString(),
        ];
    }
}
