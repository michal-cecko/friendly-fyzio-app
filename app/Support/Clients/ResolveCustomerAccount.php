<?php

namespace App\Support\Clients;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Reservations\DeactivatedClientException;
use Illuminate\Support\Str;

/**
 * Resolves the customer account behind a public sign-up (reservation wizard,
 * course/lesson/workshop enrollment): the authenticated user, an existing
 * account matching the e-mail, or a freshly created passwordless customer
 * account (docs §4.1 — the account is created automatically, credentials
 * arrive by e-mail). Deactivated clients are rejected everywhere.
 */
class ResolveCustomerAccount
{
    /**
     * @return array{0: User, 1: bool} the client and whether the account was created
     */
    public static function resolve(
        ?User $authenticated,
        string $name,
        string $email,
        ?string $phone = null,
        bool $newsletter = false,
    ): array {
        if ($authenticated !== null) {
            if ($authenticated->isDeactivated()) {
                throw new DeactivatedClientException;
            }

            return [$authenticated, false];
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            if ($existing->isDeactivated()) {
                throw new DeactivatedClientException;
            }

            if ($newsletter && $existing->newsletter_opted_in_at === null) {
                $existing->update(['newsletter_opted_in_at' => now()]);
            }

            return [$existing, false];
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'role' => UserRole::Customer,
            'password' => Str::random(40),
            'newsletter_opted_in_at' => $newsletter ? now() : null,
        ]);

        $user->clientProfile()->firstOrCreate([]);

        return [$user, true];
    }
}
