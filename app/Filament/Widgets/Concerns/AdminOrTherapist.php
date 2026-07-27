<?php

namespace App\Filament\Widgets\Concerns;

use App\Support\StaffScope;

/**
 * Restricts a dashboard widget to staff — administrators see the whole clinic,
 * a pure therapist sees the same widget narrowed to their own work by
 * {@see StaffScope}. Customers never see it.
 *
 * Contrast {@see AdminOnly}, for widgets whose figures are clinic-wide and have
 * no per-therapist reading.
 */
trait AdminOrTherapist
{
    public static function canView(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->isAdmin() || $user?->isTherapist());
    }
}
