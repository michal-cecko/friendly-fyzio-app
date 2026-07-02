<?php

namespace Tests\Feature;

use App\Mason\Bricks\InstagramBrick;
use App\Models\InstagramConnection;
use App\Models\InstagramPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstagramBrickTest extends TestCase
{
    use RefreshDatabase;

    public function test_latest_mode_renders_the_newest_n_posts(): void
    {
        $connection = InstagramConnection::factory()->create();

        for ($i = 1; $i <= 6; $i++) {
            InstagramPost::factory()->create([
                'instagram_connection_id' => $connection->getKey(),
                'permalink' => "https://instagram.com/p/post{$i}/",
                'posted_at' => now()->subDays(10 - $i),
            ]);
        }

        $html = InstagramBrick::toHtml([
            'connection_id' => $connection->getKey(),
            'source' => 'latest',
            'count' => 4,
        ]);

        // Newest four (post3..post6) shown, oldest two hidden.
        $this->assertStringContainsString('post6', $html);
        $this->assertStringContainsString('post3', $html);
        $this->assertStringNotContainsString('post2/', $html);
        $this->assertStringNotContainsString('post1/', $html);
    }

    public function test_specific_mode_renders_picked_posts_in_order(): void
    {
        $connection = InstagramConnection::factory()->create();

        $a = InstagramPost::factory()->create(['instagram_connection_id' => $connection->getKey(), 'permalink' => 'https://instagram.com/p/aaa/']);
        $b = InstagramPost::factory()->create(['instagram_connection_id' => $connection->getKey(), 'permalink' => 'https://instagram.com/p/bbb/']);
        InstagramPost::factory()->create(['instagram_connection_id' => $connection->getKey(), 'permalink' => 'https://instagram.com/p/ccc/']);

        $html = InstagramBrick::toHtml([
            'connection_id' => $connection->getKey(),
            'source' => 'specific',
            'post_ids' => [$b->getKey(), $a->getKey()],
        ]);

        $this->assertStringContainsString('bbb', $html);
        $this->assertStringContainsString('aaa', $html);
        $this->assertStringNotContainsString('ccc', $html);
        // Order preserved: bbb picked first, so it appears before aaa.
        $this->assertLessThan(strpos($html, '/p/aaa/'), strpos($html, '/p/bbb/'));
    }

    public function test_falls_back_to_legacy_images_without_a_connection(): void
    {
        $html = InstagramBrick::toHtml([
            'images' => ['https://example.test/legacy.jpg'],
        ]);

        // Media::url passes through absolute URLs, so the legacy image renders.
        $this->assertStringContainsString('https://example.test/legacy.jpg', $html);
    }
}
