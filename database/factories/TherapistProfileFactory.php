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
            'bio' => fake()->sentence(12),
            'is_collaborator' => fake()->boolean(30),
            'published_at' => fake()->boolean(80) ? now() : null,
        ];
    }
}
