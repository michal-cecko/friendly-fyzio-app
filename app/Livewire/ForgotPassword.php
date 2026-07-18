<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Livewire\Component;

/**
 * "Zapomenuté heslo": sends the reset link through the password broker.
 * Always shows the neutral "if the address exists, we sent a link" state so
 * the form can't be used to enumerate accounts. Doubles as the set-a-password
 * path for wizard-created passwordless accounts.
 */
class ForgotPassword extends Component
{
    public string $email = '';

    public bool $sent = false;

    public function send(): void
    {
        $this->validate(
            ['email' => ['required', 'email']],
            [],
            ['email' => 'e-mail'],
        );

        Password::sendResetLink(['email' => $this->email]);

        $this->sent = true;
    }

    public function render(): View
    {
        return view('livewire.forgot-password');
    }
}
