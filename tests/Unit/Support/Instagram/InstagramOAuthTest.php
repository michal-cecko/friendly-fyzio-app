<?php

namespace Tests\Unit\Support\Instagram;

use App\Support\Instagram\InstagramException;
use App\Support\Instagram\InstagramOAuth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InstagramOAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.instagram', [
            'client_id' => 'client-123',
            'client_secret' => 'secret-abc',
            'redirect' => 'https://example.test/instagram/callback',
        ]);
    }

    public function test_authorize_url_includes_client_and_state(): void
    {
        $url = app(InstagramOAuth::class)->authorizeUrl('state-token');

        $this->assertStringStartsWith('https://www.instagram.com/oauth/authorize?', $url);
        $this->assertStringContainsString('client_id=client-123', $url);
        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringContainsString('scope=instagram_business_basic', $url);
        $this->assertStringContainsString('state=state-token', $url);
        $this->assertStringContainsString(urlencode('https://example.test/instagram/callback'), $url);
    }

    public function test_exchange_code_returns_token_and_user_id(): void
    {
        Http::fake([
            'api.instagram.com/oauth/access_token' => Http::response([
                'access_token' => 'short-token',
                'user_id' => '17841400000000000',
            ]),
        ]);

        $result = app(InstagramOAuth::class)->exchangeCode('auth-code');

        $this->assertSame('short-token', $result['access_token']);
        $this->assertSame('17841400000000000', $result['user_id']);
    }

    public function test_exchange_code_throws_without_token(): void
    {
        Http::fake([
            'api.instagram.com/oauth/access_token' => Http::response(['error' => 'invalid']),
        ]);

        $this->expectException(InstagramException::class);

        app(InstagramOAuth::class)->exchangeCode('auth-code');
    }

    public function test_long_lived_token_parses_expiry(): void
    {
        Http::fake([
            'graph.instagram.com/access_token*' => Http::response([
                'access_token' => 'long-token',
                'token_type' => 'bearer',
                'expires_in' => 5184000,
            ]),
        ]);

        $result = app(InstagramOAuth::class)->toLongLivedToken('short-token');

        $this->assertSame('long-token', $result['access_token']);
        $this->assertSame(5184000, $result['expires_in']);
    }

    public function test_refresh_token_parses_expiry(): void
    {
        Http::fake([
            'graph.instagram.com/refresh_access_token*' => Http::response([
                'access_token' => 'refreshed-token',
                'expires_in' => 5184000,
            ]),
        ]);

        $result = app(InstagramOAuth::class)->refreshToken('long-token');

        $this->assertSame('refreshed-token', $result['access_token']);
        $this->assertSame(5184000, $result['expires_in']);
    }
}
