<?php

namespace Database\Factories;

use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => null,
            'reviewable_type' => null,
            'reviewable_id' => null,
            'rating' => fake()->numberBetween(3, 5),
            'content' => fake()->realText(160),
            'author_name' => fake()->name(),
            'visible' => true,
        ];
    }

    public function hidden(): static
    {
        return $this->state(['visible' => false]);
    }

    /**
     * Attach the review to a reviewable model (course, workshop, service…).
     */
    public function reviewing(Model $reviewable): static
    {
        return $this->state([
            'reviewable_type' => $reviewable->getMorphClass(),
            'reviewable_id' => $reviewable->getKey(),
        ]);
    }
}
