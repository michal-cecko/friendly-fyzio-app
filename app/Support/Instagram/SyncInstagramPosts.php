<?php

namespace App\Support\Instagram;

use App\Enums\InstagramConnectionStatus;
use App\Models\InstagramConnection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RalphJSmit\Filament\MediaLibrary\Models\MediaLibraryItem;
use Throwable;

/**
 * Synchronizes a connection's recent Instagram posts into the app: refreshes the
 * token when it is close to expiring, downloads any newly-published media into the
 * media library, and records each post so the public brick can render it.
 *
 * Stateless and Octane-safe — all state lives on the passed connection model.
 */
class SyncInstagramPosts
{
    public function __construct(
        protected InstagramOAuth $oauth,
        protected InstagramClient $client,
    ) {}

    /**
     * Sync the given connection. Returns the number of newly-stored posts.
     * On failure the connection is marked as errored and the exception rethrown.
     */
    public function __invoke(InstagramConnection $connection, int $limit = 25): int
    {
        if ($connection->needsReauthorization()) {
            $this->fail($connection, 'Chybí platný přístupový token. Autorizujte účet znovu.');
        }

        try {
            $token = $this->freshToken($connection);
            $created = 0;

            foreach ($this->client->media($token, $limit) as $media) {
                $created += $this->storePost($connection, $media) ? 1 : 0;
            }

            $connection->forceFill([
                'status' => InstagramConnectionStatus::Connected,
                'last_synced_at' => now(),
                'last_error' => null,
            ])->save();

            return $created;
        } catch (Throwable $e) {
            $this->fail($connection, $e->getMessage(), $e);
        }
    }

    /**
     * Return a usable token, refreshing (and persisting) it first if it is close
     * to expiring so long-lived tokens never lapse while a connection is active.
     */
    protected function freshToken(InstagramConnection $connection): string
    {
        if (! $connection->tokenExpiringSoon()) {
            return $connection->access_token;
        }

        $refreshed = $this->oauth->refreshToken($connection->access_token);

        $connection->forceFill([
            'access_token' => $refreshed['access_token'],
            'token_expires_at' => now()->addSeconds($refreshed['expires_in']),
        ])->save();

        return $refreshed['access_token'];
    }

    /**
     * Store a single media item if not already known. Returns true when created.
     *
     * @param  array<string, mixed>  $media
     */
    protected function storePost(InstagramConnection $connection, array $media): bool
    {
        $mediaId = (string) ($media['id'] ?? '');

        if (blank($mediaId) || $connection->posts()->where('instagram_media_id', $mediaId)->exists()) {
            return false;
        }

        $imageUrl = ($media['media_type'] ?? null) === 'VIDEO'
            ? ($media['thumbnail_url'] ?? null)
            : ($media['media_url'] ?? null);

        if (blank($imageUrl)) {
            return false;
        }

        $connection->posts()->create([
            'instagram_media_id' => $mediaId,
            'media_library_item_id' => $this->downloadImage($imageUrl, $mediaId, $media['caption'] ?? null),
            'caption' => $media['caption'] ?? null,
            'permalink' => (string) ($media['permalink'] ?? ''),
            'media_type' => (string) ($media['media_type'] ?? 'IMAGE'),
            'posted_at' => isset($media['timestamp']) ? Carbon::parse($media['timestamp']) : now(),
        ]);

        return true;
    }

    /**
     * Download the post image into the media library and return its item id, so
     * it renders through the same App\Support\Media::url() path as picked images.
     */
    protected function downloadImage(string $url, string $mediaId, ?string $caption): ?int
    {
        $response = Http::get($url);

        if ($response->failed()) {
            return null;
        }

        $item = MediaLibraryItem::create([
            'caption' => $caption,
            'alt_text' => $caption,
        ]);

        $item->addMediaFromString($response->body())
            ->usingFileName($mediaId.'.jpg')
            ->toMediaCollection('library');

        return $item->getKey();
    }

    protected function fail(InstagramConnection $connection, string $message, ?Throwable $previous = null): never
    {
        $connection->forceFill([
            'status' => InstagramConnectionStatus::Error,
            'last_error' => $message,
        ])->save();

        throw new InstagramException($message, 0, $previous);
    }
}
