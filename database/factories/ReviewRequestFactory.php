<?php

namespace Database\Factories;

use App\Enums\ReviewRequestChannel;
use App\Models\ReviewRequest;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewRequest>
 */
class ReviewRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $workshop = Workshop::factory();

        return [
            'user_id' => User::factory(),
            'reviewable_type' => (new Workshop)->getMorphClass(),
            'reviewable_id' => $workshop,
            'channel' => ReviewRequestChannel::Automatic,
            'questionnaire_url' => fake()->url(),
            'sent_at' => now(),
        ];
    }

    public function manual(): static
    {
        return $this->state(['channel' => ReviewRequestChannel::Manual]);
    }
}
