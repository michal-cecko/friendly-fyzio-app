<?php

namespace Tests\Unit\Support\Instagram;

use App\Support\Instagram\InstagramClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InstagramClientTest extends TestCase
{
    public function test_profile_returns_user_id_and_username(): void
    {
        Http::fake([
            'graph.instagram.com/me?*' => Http::response([
                'user_id' => '17841400000000000',
                'username' => 'friendlyfyzio',
            ]),
        ]);

        $profile = app(InstagramClient::class)->profile('token');

        $this->assertSame('17841400000000000', $profile['user_id']);
        $this->assertSame('friendlyfyzio', $profile['username']);
    }

    public function test_media_returns_the_data_array(): void
    {
        Http::fake([
            'graph.instagram.com/me/media*' => Http::response([
                'data' => [
                    [
                        'id' => '1',
                        'caption' => 'První',
                        'media_type' => 'IMAGE',
                        'media_url' => 'https://cdn.example/1.jpg',
                        'permalink' => 'https://instagram.com/p/1/',
                        'timestamp' => '2026-06-01T10:00:00+0000',
                    ],
                ],
            ]),
        ]);

        $media = app(InstagramClient::class)->media('token', 4);

        $this->assertCount(1, $media);
        $this->assertSame('První', $media[0]['caption']);
    }
}
