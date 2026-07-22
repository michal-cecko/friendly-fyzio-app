<?php

namespace Database\Factories;

use App\Enums\Capability;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffProfile>
 */
class StaffProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // A plain user, so the Therapist capability doesn't auto-create a
        // profile before this one is inserted (which would duplicate the slug).
        // The capability is granted afterwards — see configure().
        return [
            'user_id' => User::factory(),
            'title' => fake()->randomElement(['Fyzioterapeutka', 'Fyzioterapeut', 'Masérka', 'Lektorka']),
            'bio' => fake()->paragraphs(2, true),
            'is_collaborator' => fake()->boolean(30),
            'display_order' => 0,
            'published_at' => fake()->boolean(80) ? now() : null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (StaffProfile $profile): void {
            // Make the owner bookable. The profile already exists, so
            // ensureStaffProfile() is a no-op — no duplicate.
            $profile->user?->grantCapability(Capability::Therapist);
        });
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
