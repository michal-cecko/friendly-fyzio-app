<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Models\InvoiceSeries;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceSeries>
 */
class InvoiceSeriesFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'prefix' => strtoupper(fake()->unique()->lexify('??')),
            'document_type' => DocumentType::Invoice,
            'current_number' => 0,
            'reset_yearly' => true,
            'last_reset_year' => null,
            'padding' => 5,
            'format' => '{PREFIX}-{YEAR}-{SEQ}',
            'is_default' => false,
        ];
    }

    public function receipt(): static
    {
        return $this->state(fn (): array => [
            'document_type' => DocumentType::Receipt,
        ]);
    }

    public function asDefault(): static
    {
        return $this->state(fn (): array => [
            'is_default' => true,
        ]);
    }
}
