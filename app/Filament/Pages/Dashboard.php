<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ReservationCalendar;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

class Dashboard extends BaseDashboard
{
    public function getColumns(): int|array
    {
        return ['md' => 2];
    }

    /**
     * The reservation calendar lives on its own dedicated page, so keep it out
     * of the dashboard grid. Admins additionally drop the generic Account/Info
     * widgets for a clean clinic overview; pure therapists keep them until their
     * own dashboard ships.
     *
     * @return array<class-string<Widget> | WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        $hidden = [ReservationCalendar::class];

        if (auth()->user()?->isAdmin()) {
            $hidden[] = AccountWidget::class;
            $hidden[] = FilamentInfoWidget::class;
        }

        return array_values(array_filter(
            parent::getWidgets(),
            fn (string|WidgetConfiguration $widget): bool => ! in_array($this->normalizeWidgetClass($widget), $hidden, true),
        ));
    }
}
