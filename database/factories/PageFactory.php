<?php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim($this->faker->sentence(3), '.');

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.$this->faker->unique()->numberBetween(1, 99999),
            'content' => [],
            'sort_order' => 0,
            'is_system' => false,
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['published_at' => null]);
    }

    public function system(string $key): static
    {
        return $this->state(fn (): array => ['is_system' => true, 'system_key' => $key]);
    }
}
