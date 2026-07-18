<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * "Obnovení hesla" via the broker token. Completing the reset also marks the
 * e-mail verified when it wasn't yet — the link proves mailbox ownership, and
 * this is exactly how wizard-created passwordless customers claim their
 * account for the client zone.
 */
class ResetPassword extends Component
{
    #[Locked]
    public string $token = '';

    #[Url]
    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
    }

    public function resetPassword()
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ], [], ['email' => 'e-mail', 'password' => 'heslo']);

        $status = Password::reset(
            [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            $this->addError('email', __($status));

            return null;
        }

        $user = User::query()->where('email', $this->email)->first();

        if ($user !== null && ! $user->isDeactivated()) {
            Auth::login($user);
            session()->regenerate();

            return redirect()->to($user->isStaff() ? '/admin' : '/muj-ucet');
        }

        return redirect()->route('public.login');
    }

    public function render(): View
    {
        return view('livewire.reset-password');
    }
}
