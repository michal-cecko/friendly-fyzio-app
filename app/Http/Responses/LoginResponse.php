<?php

namespace App\Http\Responses;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;

/**
 * Sends a user to the correct panel after the single shared login:
 * staff (admin/therapist) land in the admin panel, customers in the client zone.
 */
class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        $panelId = in_array($user->role, [UserRole::Admin, UserRole::Therapist], true)
            ? 'admin'
            : 'client';

        // Redirect straight to the role's panel home rather than the "intended"
        // URL: a customer bounced off /admin would otherwise be sent back there
        // and hit a 403.
        return redirect()->to(Filament::getPanel($panelId)->getUrl());
    }
}
