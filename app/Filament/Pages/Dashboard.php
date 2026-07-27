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
     * of the dashboard grid. Staff drop the generic Account/Info widgets too —
     * everyone who treats or teaches now has real widgets of their own, so the
     * stock pair is only filler for an account with no clinic role at all.
     *
     * @return array<class-string<Widget> | WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        $hidden = [ReservationCalendar::class];
        $user = auth()->user();

        if ($user?->isAdmin() || $user?->isTherapist() || $user?->isLecturer()) {
            $hidden[] = AccountWidget::class;
            $hidden[] = FilamentInfoWidget::class;
        }

        return array_values(array_filter(
            parent::getWidgets(),
            fn (string|WidgetConfiguration $widget): bool => ! in_array($this->normalizeWidgetClass($widget), $hidden, true),
        ));
    }
}
