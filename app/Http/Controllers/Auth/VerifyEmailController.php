<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;

/**
 * Target of the signed verification link. Works without a session on purpose —
 * the link may be opened in a different browser than the one that registered;
 * the signature + e-mail hash are the proof of ownership.
 */
class VerifyEmailController extends Controller
{
    public function __invoke(string $id, string $hash): RedirectResponse
    {
        /** @var User $user */
        $user = User::query()->findOrFail($id);

        abort_unless(hash_equals(sha1($user->getEmailForVerification()), (string) $hash), 403);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        if (auth()->id() === $user->getKey()) {
            return redirect()->to('/muj-ucet');
        }

        return redirect()->route('public.login', ['return' => '/muj-ucet'])
            ->with('status', 'E-mail byl ověřen. Nyní se můžete přihlásit.');
    }
}
