<?php

namespace App\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;

/**
 * The admin panel has no login page of its own — the whole app shares the single
 * login hosted on the client panel. Unauthenticated visitors to /admin are sent
 * there instead of to a non-existent admin login route.
 */
class RedirectToClientLogin extends FilamentAuthenticate
{
    protected function redirectTo($request): ?string
    {
        return Filament::getPanel('client')->getLoginUrl();
    }
}
