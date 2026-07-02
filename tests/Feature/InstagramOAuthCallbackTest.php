<?php

namespace Tests\Feature;

use App\Enums\InstagramConnectionStatus;
use App\Jobs\SyncInstagramConnectionJob;
use App\Models\InstagramConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InstagramOAuthCallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.instagram', [
            'client_id' => 'client-123',
            'client_secret' => 'secret-abc',
            'redirect' => 'https://example.test/instagram/callback',
        ]);
    }

    public function test_redirect_route_sends_admin_to_instagram(): void
    {
        $connection = InstagramConnection::factory()->pending()->create();

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('instagram.oauth.redirect', $connection));

        $response->assertRedirectContains('https://www.instagram.com/oauth/authorize');
    }

    public function test_redirect_route_requires_authentication(): void
    {
        $connection = InstagramConnection::factory()->pending()->create();

        $this->get(route('instagram.oauth.redirect', $connection))->assertRedirect();
    }

    public function test_callback_stores_token_and_connects_account(): void
    {
        Queue::fake();

        $connection = InstagramConnection::factory()->pending()->create();

        Http::fake([
            'api.instagram.com/oauth/access_token' => Http::response([
                'access_token' => 'short-token',
                'user_id' => '17841400000000000',
            ]),
            'graph.instagram.com/access_token*' => Http::response([
                'access_token' => 'long-lived-token',
                'expires_in' => 5184000,
            ]),
            'graph.instagram.com/me?*' => Http::response([
                'user_id' => '17841400000000000',
                'username' => 'friendlyfyzio',
            ]),
        ]);

        $state = Crypt::encryptString($connection->getKey());

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('instagram.oauth.callback', ['code' => 'auth-code', 'state' => $state]));

        $response->assertRedirect();

        $connection->refresh();
        $this->assertSame(InstagramConnectionStatus::Connected, $connection->status);
        $this->assertSame('friendlyfyzio', $connection->username);
        $this->assertSame('long-lived-token', $connection->access_token);
        $this->assertNotNull($connection->token_expires_at);

        Queue::assertPushed(SyncInstagramConnectionJob::class);
    }

    public function test_callback_with_invalid_state_does_not_connect(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('instagram.oauth.callback', ['code' => 'auth-code', 'state' => 'garbage']));

        $response->assertRedirect();
        $this->assertDatabaseCount('instagram_connections', 0);
    }
}
