<?php

namespace App\Filament\Support\Schemas;

use DiscoveryDesign\FilamentGaze\Forms\Components\GazeBanner;

class PresenceBanner
{
    /**
     * A reusable "who else is here" banner (powered by the Gaze plugin) that
     * shows the names of other staff members currently viewing or editing the
     * same record, so two people don't unknowingly edit it at once.
     *
     * Only rendered for staff (administrators and therapists) and hidden on
     * create pages, where there is no shared record to collide over. It is a
     * display-only field, so its state is never written back to the model.
     */
    public static function make(): GazeBanner
    {
        return GazeBanner::make()
            ->dehydrated(false)
            ->hideOnCreate()
            ->visible(fn (): bool => (bool) auth()->user()?->isStaff())
            ->columnSpanFull();
    }
}
