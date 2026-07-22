<?php

namespace App\Filament\Widgets\Concerns;

/**
 * Restricts a dashboard widget to admins. Pure therapists (and the acts-as
 * variant is still an Admin) get their own dashboard later; until then they see
 * none of these widgets.
 */
trait AdminOnly
{
    public static function canView(): bool
    {
        return auth()->user()?->isAdmin();
    }
}
