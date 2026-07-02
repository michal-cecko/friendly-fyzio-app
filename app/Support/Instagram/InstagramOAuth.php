<?php

namespace App\Support\Instagram;

use Illuminate\Support\Facades\Http;

/**
 * Drives the OAuth handshake for the "Instagram API with Instagram Login" flow:
 * building the consent URL, exchanging the returned code for a short-lived token,
 * upgrading it to a long-lived (60-day) token, and refreshing it before expiry.
 *
 * Stateless and Octane-safe — credentials are read from config on each call and
 * nothing is cached on the instance.
 */
class InstagramOAuth
{
    protected const AUTHORIZE_URL = 'https://www.instagram.com/oauth/authorize';

    protected const TOKEN_URL = 'https://api.instagram.com/oauth/access_token';

    protected const GRAPH_URL = 'https://graph.instagram.com';

    /**
     * Scopes required to read the connected account's own media.
     */
    protected const SCOPES = 'instagram_business_basic';

    /**
     * Build the consent-screen URL the admin is redirected to. The opaque $state
     * is echoed back to the callback and used to identify the connection.
     */
    public function authorizeUrl(string $state): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'state' => $state,
        ]);
    }

    /**
     * Exchange the authorization code for a short-lived access token.
     *
     * @return array{access_token: string, user_id: string}
     */
    public function exchangeCode(string $code): array
    {
        $response = Http::asForm()
            ->throw()
            ->post(self::TOKEN_URL, [
                'client_id' => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'grant_type' => 'authorization_code',
                'redirect_uri' => $this->redirectUri(),
                'code' => $code,
            ]);

        $token = $response->json('access_token');
        $userId = $response->json('user_id');

        if (blank($token)) {
            throw new InstagramException('Instagram did not return an access token.');
        }

        return [
            'access_token' => (string) $token,
            'user_id' => (string) $userId,
        ];
    }

    /**
     * Upgrade a short-lived token to a long-lived (60-day) token.
     *
     * @return array{access_token: string, expires_in: int}
     */
    public function toLongLivedToken(string $shortLivedToken): array
    {
        $response = Http::throw()->get(self::GRAPH_URL.'/access_token', [
            'grant_type' => 'ig_exchange_token',
            'client_secret' => $this->clientSecret(),
            'access_token' => $shortLivedToken,
        ]);

        return $this->parseToken($response->json());
    }

    /**
     * Refresh a long-lived token, extending it for another 60 days.
     *
     * @return array{access_token: string, expires_in: int}
     */
    public function refreshToken(string $longLivedToken): array
    {
        $response = Http::throw()->get(self::GRAPH_URL.'/refresh_access_token', [
            'grant_type' => 'ig_refresh_token',
            'access_token' => $longLivedToken,
        ]);

        return $this->parseToken($response->json());
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array{access_token: string, expires_in: int}
     */
    protected function parseToken(?array $payload): array
    {
        $token = $payload['access_token'] ?? null;

        if (blank($token)) {
            throw new InstagramException('Instagram did not return an access token.');
        }

        return [
            'access_token' => (string) $token,
            'expires_in' => (int) ($payload['expires_in'] ?? 0),
        ];
    }

    protected function clientId(): string
    {
        return (string) config('services.instagram.client_id');
    }

    protected function clientSecret(): string
    {
        return (string) config('services.instagram.client_secret');
    }

    protected function redirectUri(): string
    {
        return (string) config('services.instagram.redirect');
    }
}
