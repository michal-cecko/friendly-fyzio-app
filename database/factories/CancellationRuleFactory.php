<?php

namespace Database\Factories;

use App\Models\CancellationRule;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CancellationRule>
 */
class CancellationRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'cancel_before_hours' => fake()->randomElement([12, 24, 48]),
        ];
    }
}
