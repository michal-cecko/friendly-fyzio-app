<?php

namespace Database\Factories;

use App\Enums\ReviewRequestChannel;
use App\Models\Lesson;
use App\Models\ReviewRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
        return [
            'user_id' => User::factory(),
            'reviewable_type' => (new Lesson)->getMorphClass(),
            'reviewable_id' => Lesson::factory(),
            'channel' => ReviewRequestChannel::Automatic,
            'token' => Str::random(48),
            'sent_at' => now(),
            'completed_at' => null,
            'review_id' => null,
        ];
    }

    public function manual(): static
    {
        return $this->state(['channel' => ReviewRequestChannel::Manual]);
    }

    public function completed(): static
    {
        return $this->state(['completed_at' => now()]);
    }
}
