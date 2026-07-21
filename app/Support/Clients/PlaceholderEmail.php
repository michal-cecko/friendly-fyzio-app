<?php

namespace App\Support\Clients;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Placeholder e-mail for clients created without one (admin calendar inline
 * client creation): jmeno.prijmeni{4 digits}@friendlyfyzio.cz. Guaranteed
 * unique against users.email — the DB unique index also covers soft-deleted
 * rows, so the probe includes them.
 */
class PlaceholderEmail
{
    public const string DOMAIN = 'friendlyfyzio.cz';

    public function generate(string $firstName, string $lastName): string
    {
        $base = trim(Str::slug($firstName).'.'.Str::slug($lastName), '.');
        $base = $base !== '' ? $base : 'klient';

        do {
            $email = $base.$this->digits().'@'.self::DOMAIN;
        } while (User::withTrashed()->where('email', $email)->exists());

        return $email;
    }

    protected function digits(): string
    {
        return sprintf('%04d', random_int(0, 9999));
    }
}
