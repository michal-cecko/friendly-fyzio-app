<?php

namespace Database\Factories;

use App\Enums\DayOfWeek;
use App\Enums\WeekType;
use App\Models\Room;
use App\Models\RoomBlocking;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomBlocking>
 */
class RoomBlockingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(1, 14))->setTime(fake()->numberBetween(8, 16), 0);

        return [
            'room_id' => Room::factory(),
            'reason' => fake()->randomElement(['Úklid', 'Údržba', 'Školení', 'Rezervováno']),
            'is_recurring' => false,
            'day_of_week' => null,
            'week_type' => WeekType::All,
            'start_time' => null,
            'end_time' => null,
            'start_at' => $start,
            'end_at' => $start->copy()->addHours(2),
        ];
    }

    public function recurring(): static
    {
        return $this->state(fn (): array => [
            'is_recurring' => true,
            'day_of_week' => fake()->randomElement(DayOfWeek::cases()),
            'week_type' => WeekType::All,
            'start_time' => '16:00',
            'end_time' => '18:00',
            'start_at' => null,
            'end_at' => null,
        ]);
    }
}
