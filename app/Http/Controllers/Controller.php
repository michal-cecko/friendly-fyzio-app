<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;

abstract class Controller
{
    /**
     * Whether the current user may preview unpublished public content.
     */
    protected function canPreview(): bool
    {
        $user = auth()->user();

        return $user !== null && in_array($user->role, [UserRole::Admin, UserRole::Therapist], true);
    }
}
