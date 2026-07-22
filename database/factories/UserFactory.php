<?php

namespace Database\Factories;

use App\Enums\Capability;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+420 '.fake()->numerify('### ### ###'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'newsletter_opted_in_at' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Capabilities are Spatie roles, so they must be assigned after the user
     * exists. Each state records the wanted state and a single afterCreating
     * hook applies it — states compose, e.g. ->admin()->therapist().
     */
    protected function grant(Capability $capability): static
    {
        return $this->afterCreating(fn (User $user) => $user->grantCapability($capability));
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->grant(Capability::Admin);
    }

    public function therapist(): static
    {
        return $this->grant(Capability::Therapist);
    }

    public function lecturer(): static
    {
        return $this->grant(Capability::Lecturer);
    }

    public function customer(): static
    {
        return $this->afterCreating(fn (User $user) => $user->markAsCustomer());
    }
}
