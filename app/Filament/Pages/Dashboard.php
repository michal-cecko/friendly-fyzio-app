<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ReservationCalendar;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

class Dashboard extends BaseDashboard
{
    /**
     * The reservation calendar lives on its own dedicated page, so keep it out
     * of the dashboard widget grid.
     *
     * @return array<class-string<Widget> | WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return array_values(array_filter(
            parent::getWidgets(),
            fn (string|WidgetConfiguration $widget): bool => $this->normalizeWidgetClass($widget) !== ReservationCalendar::class,
        ));
    }
}
