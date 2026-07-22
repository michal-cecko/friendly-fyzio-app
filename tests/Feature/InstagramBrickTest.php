<?php

namespace Tests\Feature;

use App\Mason\Bricks\InstagramBrick;
use App\Models\InstagramConnection;
use App\Models\InstagramPost;
use App\Models\User;
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

    public function test_legacy_images_alone_are_treated_as_a_placeholder(): void
    {
        // Legacy manually-picked images (old seeded demo data) no longer count as
        // real content: a block with only those is a placeholder, so a guest sees
        // nothing rather than the old fallback images.
        $this->assertSame('', InstagramBrick::toHtml([
            'images' => ['https://example.test/legacy.jpg'],
        ]));
    }

    public function test_legacy_images_do_not_render_for_admins_either(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $html = InstagramBrick::toHtml([
            'images' => ['https://example.test/legacy.jpg'],
        ]);

        // Admin sees the placeholder warning, never the legacy image.
        $this->assertStringNotContainsString('https://example.test/legacy.jpg', $html);
        $this->assertStringContainsString('zástupný náhled', $html);
    }

    public function test_placeholder_brick_renders_nothing_for_guests(): void
    {
        $this->assertSame('', InstagramBrick::toHtml([]));
    }

    public function test_placeholder_brick_renders_nothing_for_non_admins(): void
    {
        $this->actingAs(User::factory()->customer()->create());

        $this->assertSame('', InstagramBrick::toHtml([]));
    }

    public function test_placeholder_brick_shows_warning_with_connect_link_to_admins(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $html = InstagramBrick::toHtml([]);

        $this->assertStringContainsString('zástupný náhled', $html);
        $this->assertStringContainsString('Propojit Instagram účet', $html);
        $this->assertStringContainsString('instagram-connections', $html);
    }

    public function test_brick_with_posts_renders_without_warning_for_admins(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $connection = InstagramConnection::factory()->create();
        InstagramPost::factory()->create([
            'instagram_connection_id' => $connection->getKey(),
            'permalink' => 'https://instagram.com/p/real/',
        ]);

        $html = InstagramBrick::toHtml([
            'connection_id' => $connection->getKey(),
            'source' => 'latest',
            'count' => 4,
        ]);

        $this->assertStringContainsString('/p/real/', $html);
        $this->assertStringNotContainsString('zástupný náhled', $html);
    }
}
