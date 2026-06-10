<?php

namespace Database\Factories;

use App\Enums\SettingValueType;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => Str::slug(fake()->unique()->words(2, true), '.'),
            'value' => fake()->word(),
            'type' => SettingValueType::Text,
            'label' => fake()->words(2, true),
            'group' => fake()->randomElement(['Rezervace', 'Web', null]),
            'description' => null,
            'config' => null,
            'sort' => 0,
        ];
    }
}
