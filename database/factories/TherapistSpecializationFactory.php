<?php

namespace Database\Factories;

use App\Models\Specialization;
use App\Models\StaffProfile;
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
            'therapist_id' => StaffProfile::factory(),
            'specialization_id' => Specialization::factory(),
            'display_order' => 0,
        ];
    }
}
