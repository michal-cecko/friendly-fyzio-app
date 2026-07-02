<?php

namespace Database\Factories;

use App\Models\InstagramConnection;
use App\Models\InstagramPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstagramPost>
 */
class InstagramPostFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'instagram_connection_id' => InstagramConnection::factory(),
            'instagram_media_id' => (string) $this->faker->numerify('##################'),
            'media_library_item_id' => null,
            'caption' => $this->faker->sentence(),
            'permalink' => 'https://www.instagram.com/p/'.$this->faker->lexify('???????????').'/',
            'media_type' => 'IMAGE',
            'posted_at' => $this->faker->dateTimeBetween('-30 days'),
        ];
    }
}
