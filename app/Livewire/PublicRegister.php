<?php

namespace App\Livewire;

use App\Models\User;
use App\Rules\TurnstileTokenValid;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

/**
 * Public self-service registration for the client zone. Every account created
 * here is a customer (staff accounts are provisioned from the admin panel);
 * the new account starts unverified and lands on the verification notice.
 */
class PublicRegister extends Component
{
    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $newsletter = false;

    public ?string $turnstileToken = null;

    public function register()
    {
        $this->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'turnstileToken' => ['required', new TurnstileTokenValid],
        ], [], [
            'first_name' => 'jméno',
            'last_name' => 'příjmení',
            'email' => 'e-mail',
            'phone' => 'telefon',
            'password' => 'heslo',
            'turnstileToken' => 'ověření proti robotům',
        ]);

        $user = User::create([
            'name' => trim("{$this->first_name} {$this->last_name}"),
            'email' => $this->email,
            'phone' => $this->phone,
            'password' => $this->password,
            'newsletter_opted_in_at' => $this->newsletter ? now() : null,
        ]);

        $user->markAsCustomer();
        $user->clientProfile()->create();

        event(new Registered($user));

        Auth::login($user);
        session()->regenerate();

        return redirect()->route('verification.notice');
    }

    public function render(): View
    {
        return view('livewire.public-register');
    }
}
