<?php

namespace Database\Factories;

use App\Models\ReservationDayWaitlistEntry;
use App\Models\Service;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReservationDayWaitlistEntry>
 */
class ReservationDayWaitlistEntryFactory extends Factory
{
    protected $model = ReservationDayWaitlistEntry::class;

    public function definition(): array
    {
        return [
            'client_id' => null,
            'therapist_id' => TherapistProfile::factory(),
            'service_id' => Service::factory(),
            'reservation_date' => today()->addWeek()->toDateString(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+420604793255',
            'notified_at' => null,
        ];
    }

    public function anyTherapist(): static
    {
        return $this->state(['therapist_id' => null]);
    }

    public function forClient(User $client): static
    {
        return $this->state([
            'client_id' => $client->getKey(),
            'name' => $client->name,
            'email' => $client->email,
        ]);
    }

    public function notified(): static
    {
        return $this->state(['notified_at' => now()]);
    }
}
