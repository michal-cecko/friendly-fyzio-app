<?php

namespace Database\Factories;

use App\Models\SuggestionDismissal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SuggestionDismissal>
 */
class SuggestionDismissalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['doctor_note_pending', 'reviews_hidden', 'payments_past_due']);

        return [
            'key' => $type,
            'type' => $type,
            'fingerprint' => '',
            'snoozed_until' => null,
            'dismissed_by' => User::factory()->admin(),
        ];
    }

    /**
     * Hidden only for a while — how an aggregate card is put away.
     */
    public function snoozed(): static
    {
        return $this->state(fn (): array => ['snoozed_until' => now()->addWeek()]);
    }

    /**
     * A snooze that has already run out, so it hides nothing.
     */
    public function expired(): static
    {
        return $this->state(fn (): array => ['snoozed_until' => now()->subDay()]);
    }
}
