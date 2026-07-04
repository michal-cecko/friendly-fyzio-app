<?php

namespace Database\Factories;

use App\Enums\ContactInquiryStatus;
use App\Models\ContactInquiry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactInquiry>
 */
class ContactInquiryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake('cs_CZ')->name(),
            'email' => fake()->safeEmail(),
            'phone' => '+420 '.fake()->numerify('### ### ###'),
            'message' => fake('cs_CZ')->paragraph(),
            'status' => ContactInquiryStatus::New,
        ];
    }

    /**
     * Mark the inquiry as being handled.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ContactInquiryStatus::InProgress,
        ]);
    }

    /**
     * Mark the inquiry as resolved.
     */
    public function handled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ContactInquiryStatus::Handled,
        ]);
    }
}
