<?php

namespace Database\Factories;

use App\Enums\InstagramConnectionStatus;
use App\Models\InstagramConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstagramConnection>
 */
class InstagramConnectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => $this->faker->userName(),
            'instagram_user_id' => (string) $this->faker->numerify('###############'),
            'access_token' => 'IGQV'.$this->faker->sha256(),
            'token_expires_at' => now()->addDays(60),
            'status' => InstagramConnectionStatus::Connected,
            'last_synced_at' => now(),
            'last_error' => null,
            'is_active' => true,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => InstagramConnectionStatus::Pending,
            'access_token' => null,
            'token_expires_at' => null,
            'last_synced_at' => null,
        ]);
    }

    public function errored(): static
    {
        return $this->state(fn (): array => [
            'status' => InstagramConnectionStatus::Error,
            'last_error' => 'Token expired.',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
