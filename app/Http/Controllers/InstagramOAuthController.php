<?php

namespace App\Http\Controllers;

use App\Enums\InstagramConnectionStatus;
use App\Filament\Clusters\Obsah\Resources\InstagramConnections\InstagramConnectionResource;
use App\Jobs\SyncInstagramConnectionJob;
use App\Models\InstagramConnection;
use App\Support\Instagram\InstagramClient;
use App\Support\Instagram\InstagramOAuth;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Handles the Instagram "Instagram API with Instagram Login" OAuth handshake:
 * kicks the admin over to the consent screen and processes the callback,
 * persisting the long-lived token on the connection and kicking off an initial
 * sync. The connection id is carried through the opaque, encrypted `state`.
 */
class InstagramOAuthController extends Controller
{
    public function __construct(
        protected InstagramOAuth $oauth,
        protected InstagramClient $client,
    ) {}

    public function redirect(InstagramConnection $connection): RedirectResponse
    {
        if (! Auth::check()) {
            return redirect('/admin');
        }

        return redirect()->away(
            $this->oauth->authorizeUrl(Crypt::encryptString($connection->getKey()))
        );
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! Auth::check()) {
            return redirect('/admin');
        }

        $connection = $this->resolveConnection($request->query('state'));

        if (! $connection) {
            Notification::make()
                ->title('Neplatný nebo prošlý autorizační požadavek.')
                ->danger()
                ->send();

            return redirect(InstagramConnectionResource::getUrl('index'));
        }

        if (blank($request->query('code'))) {
            return $this->fail($connection, 'Autorizace na Instagramu byla zrušena.');
        }

        try {
            $short = $this->oauth->exchangeCode((string) $request->query('code'));
            $long = $this->oauth->toLongLivedToken($short['access_token']);
            $profile = $this->client->profile($long['access_token']);

            $connection->forceFill([
                'username' => $profile['username'],
                'instagram_user_id' => $profile['user_id'],
                'access_token' => $long['access_token'],
                'token_expires_at' => now()->addSeconds($long['expires_in']),
                'status' => InstagramConnectionStatus::Connected,
                'last_error' => null,
            ])->save();
        } catch (Throwable $e) {
            return $this->fail($connection, 'Autorizaci se nepodařilo dokončit: '.$e->getMessage());
        }

        SyncInstagramConnectionJob::dispatch($connection);

        Notification::make()
            ->title("Účet @{$connection->username} byl připojen.")
            ->success()
            ->send();

        return redirect(InstagramConnectionResource::getUrl('edit', ['record' => $connection]));
    }

    protected function resolveConnection(?string $state): ?InstagramConnection
    {
        if (blank($state)) {
            return null;
        }

        try {
            return InstagramConnection::find(Crypt::decryptString($state));
        } catch (DecryptException) {
            return null;
        }
    }

    protected function fail(InstagramConnection $connection, string $message): RedirectResponse
    {
        $connection->forceFill([
            'status' => InstagramConnectionStatus::Error,
            'last_error' => $message,
        ])->save();

        Notification::make()
            ->title($message)
            ->danger()
            ->send();

        return redirect(InstagramConnectionResource::getUrl('edit', ['record' => $connection]));
    }
}
