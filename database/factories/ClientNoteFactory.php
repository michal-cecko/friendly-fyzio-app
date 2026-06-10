<?php

namespace Database\Factories;

use App\Models\ClientNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientNote>
 */
class ClientNoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => User::factory()->customer(),
            'author_id' => User::factory()->therapist(),
            'content' => fake()->paragraph(),
        ];
    }
}
