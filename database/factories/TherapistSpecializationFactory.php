<?php

namespace Database\Factories;

use App\Models\TherapistProfile;
use App\Models\TherapistSpecialization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TherapistSpecialization>
 */
class TherapistSpecializationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'therapist_id' => TherapistProfile::factory(),
            'name' => fake()->randomElement([
                'Pánevní dno',
                'Těhotenství & porod',
                'Dětská fyzioterapie',
                'Ortopedická rehabilitace',
                'Sport',
                'Jóga',
                'SM systém',
                'Pilates',
                'Lymfodrenáž',
                'Relaxační masáže',
            ]),
            'icon' => fake()->randomElement(['heart', 'user', 'star', 'zap', 'activity']),
            'description' => fake()->sentence(12),
            'display_order' => 0,
        ];
    }
}
