<?php

namespace Database\Factories;

use App\Models\WaitlistEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<WaitlistEntry>
 */
class WaitlistEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => null,
            'name' => $this->faker->name(),
            'email' => $this->faker->safeEmail(),
            'phone' => '+420'.$this->faker->numberBetween(600000000, 799999999),
            'notified_at' => null,
            'confirmed_at' => null,
        ];
    }

    /**
     * Attach the entry to a given waitlistable offer (course, série, event…).
     */
    public function forWaitlistable(Model $waitlistable): static
    {
        return $this->state(fn (): array => [
            'waitlistable_type' => $waitlistable->getMorphClass(),
            'waitlistable_id' => $waitlistable->getKey(),
        ]);
    }
}
