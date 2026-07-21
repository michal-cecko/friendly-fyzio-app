<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;

class Login extends BaseLogin
{
    /**
     * Hide the in-page header logo; the auth layout already renders the brand
     * logo above the form, so showing it here would duplicate it.
     */
    public function hasLogo(): bool
    {
        return false;
    }
}
