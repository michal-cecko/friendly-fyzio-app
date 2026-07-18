<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The public login on the web guard — the single sign-in for customers (staff
 * are redirected on to /admin). Doubles as the "forgotten password" entry point
 * and the redirect target the wizard sends users to; the `return` parameter
 * round-trips the wizard's URL-encoded state so nothing is lost across the
 * redirect, and defaults to the client zone.
 */
class PublicLogin extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    #[Url]
    public ?string $return = null;

    public function authenticate()
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ], [], ['email' => 'e-mail', 'password' => 'heslo']);

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            $this->addError('email', 'Nesprávný e-mail nebo heslo.');

            return null;
        }

        /** @var User $user */
        $user = Auth::user();

        if ($user->isDeactivated()) {
            Auth::logout();
            $this->addError('email', 'Váš účet byl deaktivován. Pro obnovení přístupu nás prosím kontaktujte.');

            return null;
        }

        session()->regenerate();

        if ($user->isStaff()) {
            return redirect()->to('/admin');
        }

        return redirect()->to($this->safeReturn());
    }

    /**
     * Only allow local relative paths as the post-login redirect target;
     * default to the client zone.
     */
    protected function safeReturn(): string
    {
        $return = (string) $this->return;

        return str_starts_with($return, '/') && ! str_starts_with($return, '//') ? $return : '/muj-ucet';
    }

    public function render(): View
    {
        return view('livewire.public-login');
    }
}
