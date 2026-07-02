<?php

namespace App\Support\Instagram;

use Illuminate\Support\Facades\Http;

/**
 * Reads data from the Instagram Graph API on behalf of a connected account.
 *
 * Stateless and Octane-safe — the caller passes the access token to each method
 * and nothing is cached on the instance.
 */
class InstagramClient
{
    protected const GRAPH_URL = 'https://graph.instagram.com';

    protected const MEDIA_FIELDS = 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp';

    /**
     * Fetch the connected account's profile (its numeric id and @username).
     *
     * @return array{user_id: string, username: string}
     */
    public function profile(string $token): array
    {
        $response = Http::throw()->get(self::GRAPH_URL.'/me', [
            'fields' => 'user_id,username',
            'access_token' => $token,
        ]);

        return [
            'user_id' => (string) $response->json('user_id'),
            'username' => (string) $response->json('username'),
        ];
    }

    /**
     * Fetch the account's most recent media, newest first.
     *
     * @return array<int, array{id: string, caption: ?string, media_type: string, media_url: ?string, thumbnail_url: ?string, permalink: string, timestamp: string}>
     */
    public function media(string $token, int $limit = 25): array
    {
        $response = Http::throw()->get(self::GRAPH_URL.'/me/media', [
            'fields' => self::MEDIA_FIELDS,
            'access_token' => $token,
            'limit' => $limit,
        ]);

        return $response->json('data', []);
    }
}
