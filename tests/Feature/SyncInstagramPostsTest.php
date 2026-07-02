<?php

namespace Tests\Feature;

use App\Enums\InstagramConnectionStatus;
use App\Models\InstagramConnection;
use App\Support\Instagram\InstagramException;
use App\Support\Instagram\SyncInstagramPosts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SyncInstagramPostsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A valid 1×1 PNG so the media-library image conversions succeed.
     */
    protected function fakePng(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    }

    protected function fakeInstagram(array $media): void
    {
        Http::fake([
            'graph.instagram.com/me/media*' => Http::response(['data' => $media]),
            '*' => Http::response($this->fakePng()),
        ]);
    }

    public function test_sync_downloads_posts_and_stores_media(): void
    {
        Storage::fake('public');

        $connection = InstagramConnection::factory()->create();

        $this->fakeInstagram([
            [
                'id' => '111',
                'caption' => 'Ahoj',
                'media_type' => 'IMAGE',
                'media_url' => 'https://cdn.example/111.jpg',
                'permalink' => 'https://instagram.com/p/111/',
                'timestamp' => '2026-06-01T10:00:00+0000',
            ],
            [
                'id' => '222',
                'caption' => 'Video',
                'media_type' => 'VIDEO',
                'media_url' => 'https://cdn.example/222.mp4',
                'thumbnail_url' => 'https://cdn.example/222.jpg',
                'permalink' => 'https://instagram.com/p/222/',
                'timestamp' => '2026-06-02T10:00:00+0000',
            ],
        ]);

        $created = app(SyncInstagramPosts::class)($connection);

        $this->assertSame(2, $created);
        $this->assertSame(2, $connection->posts()->count());

        $video = $connection->posts()->where('instagram_media_id', '222')->first();
        $this->assertSame('VIDEO', $video->media_type);
        $this->assertNotNull($video->media_library_item_id);

        $connection->refresh();
        $this->assertSame(InstagramConnectionStatus::Connected, $connection->status);
        $this->assertNotNull($connection->last_synced_at);
    }

    public function test_sync_is_idempotent_for_known_media(): void
    {
        Storage::fake('public');

        $connection = InstagramConnection::factory()->create();

        $media = [[
            'id' => '111',
            'caption' => 'Ahoj',
            'media_type' => 'IMAGE',
            'media_url' => 'https://cdn.example/111.jpg',
            'permalink' => 'https://instagram.com/p/111/',
            'timestamp' => '2026-06-01T10:00:00+0000',
        ]];

        $this->fakeInstagram($media);

        app(SyncInstagramPosts::class)($connection);
        $secondRun = app(SyncInstagramPosts::class)($connection);

        $this->assertSame(0, $secondRun);
        $this->assertSame(1, $connection->posts()->count());
    }

    public function test_sync_marks_connection_errored_on_api_failure(): void
    {
        $connection = InstagramConnection::factory()->create();

        Http::fake([
            'graph.instagram.com/me/media*' => Http::response('nope', 500),
        ]);

        try {
            app(SyncInstagramPosts::class)($connection);
            $this->fail('Expected InstagramException.');
        } catch (InstagramException) {
            // expected
        }

        $connection->refresh();
        $this->assertSame(InstagramConnectionStatus::Error, $connection->status);
        $this->assertNotNull($connection->last_error);
    }

    public function test_sync_requires_authorization(): void
    {
        $connection = InstagramConnection::factory()->pending()->create();

        $this->expectException(InstagramException::class);

        app(SyncInstagramPosts::class)($connection);
    }
}
