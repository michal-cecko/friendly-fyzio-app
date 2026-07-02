<?php

namespace Database\Factories;

use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TherapistProfile>
 */
class TherapistProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->therapist(),
            'title' => fake()->randomElement(['Fyzioterapeutka', 'Fyzioterapeut', 'Masérka', 'Lektorka']),
            'bio' => fake()->paragraphs(2, true),
            'is_collaborator' => fake()->boolean(30),
            'display_order' => 0,
            'published_at' => fake()->boolean(80) ? now() : null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['published_at' => now()]);
    }

    public function unpublished(): static
    {
        return $this->state(fn (): array => ['published_at' => null]);
    }
}
