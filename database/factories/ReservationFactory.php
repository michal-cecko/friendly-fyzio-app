<?php

namespace Database\Factories;

use App\Enums\ConfirmationSource;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Service;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->numberBetween(8, 17);

        return [
            'client_id' => User::factory()->customer(),
            'service_id' => Service::factory(),
            'therapist_id' => TherapistProfile::factory(),
            'room_id' => Room::factory(),
            'reservation_date' => fake()->dateTimeBetween('-3 days', '+10 days')->format('Y-m-d'),
            'start_time' => sprintf('%02d:00', $start),
            'end_time' => sprintf('%02d:00', $start + 1),
            'status' => fake()->randomElement(ReservationStatus::cases()),
            'payment_status' => fake()->randomElement(PaymentStatus::cases()),
            'is_control_therapy' => fake()->boolean(15),
            'notes' => fake()->boolean(30) ? fake()->sentence() : null,
        ];
    }

    public function confirmed(ConfirmationSource $by = ConfirmationSource::Customer): static
    {
        return $this->state(fn (): array => [
            'status' => ReservationStatus::Confirmed,
            'confirmed_at' => now(),
            'confirmed_by' => $by,
            'confirmed_by_id' => $by === ConfirmationSource::Automatic ? null : User::factory(),
        ]);
    }
}
