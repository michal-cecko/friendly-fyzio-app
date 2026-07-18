<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * "Ověřte svůj e-mail" gate page: shown to authenticated-but-unverified users
 * before they can enter the client zone. Offers a rate-limited resend.
 */
class VerifyEmailNotice extends Component
{
    public bool $resent = false;

    public function mount()
    {
        if ($this->user()->hasVerifiedEmail()) {
            return redirect()->to('/muj-ucet');
        }

        return null;
    }

    public function resend(): void
    {
        $executed = RateLimiter::attempt(
            'verification-resend:'.$this->user()->getKey(),
            1,
            fn () => $this->user()->sendEmailVerificationNotification(),
            60,
        );

        if (! $executed) {
            $this->addError('resend', 'E-mail jsme odeslali před chvílí — zkuste to prosím za minutu.');

            return;
        }

        $this->resent = true;
    }

    protected function user(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function render(): View
    {
        return view('livewire.verify-email-notice', [
            'email' => $this->user()->email,
        ]);
    }
}
