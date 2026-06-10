<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;

/**
 * Validates a Cloudflare Turnstile token against the siteverify endpoint.
 * Fails closed: a missing secret or an unsuccessful verdict rejects the token.
 */
class TurnstileTokenValid implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $secret = config('services.turnstile.secret_key');

        if (blank($secret) || blank($value)) {
            $fail(__('Ověření proti robotům selhalo. Zkuste to prosím znovu.'));

            return;
        }

        $response = Http::asForm()
            ->throw(fn () => $fail(__('Ověření proti robotům se nezdařilo. Zkuste to prosím znovu.')))
            ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secret,
                'response' => $value,
                'remoteip' => request()->ip(),
            ]);

        if ($response->json('success') !== true) {
            $fail(__('Ověření proti robotům selhalo. Zkuste to prosím znovu.'));
        }
    }
}
