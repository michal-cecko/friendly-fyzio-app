<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\User;

/**
 * Restricts a widget showing aggregate money figures to holders of the
 * Revenue capability. Deliberately not tied to admin status — see
 * {@see User::canViewRevenue()}.
 */
trait RevenueOnly
{
    public static function canView(): bool
    {
        return auth()->user()?->canViewRevenue() ?? false;
    }
}
