<?php

namespace Database\Factories;

use App\Enums\BannerType;
use App\Models\Banner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Banner>
 */
class BannerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => rtrim($this->faker->sentence(2), '.'),
            'type' => BannerType::Topbar,
            'placement' => 'all',
            'page_ids' => null,
            'content' => ['title' => rtrim($this->faker->sentence(4), '.')],
            'is_active' => true,
            'active_from' => null,
            'active_to' => null,
            'sort_order' => 0,
        ];
    }

    public function ofType(BannerType $type): static
    {
        return $this->state(fn (): array => ['type' => $type]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function forPages(array $pageIds): static
    {
        return $this->state(fn (): array => ['placement' => 'specific', 'page_ids' => $pageIds]);
    }
}
