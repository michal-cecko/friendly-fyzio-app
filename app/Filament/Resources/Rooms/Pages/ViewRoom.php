<?php

namespace App\Filament\Resources\Rooms\Pages;

use App\Filament\Resources\Rooms\RoomResource;
use App\Filament\Widgets\ReservationCalendar;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRoom extends ViewRecord
{
    protected static string $resource = RoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    /**
     * Room-scoped calendar below the infolist + relation-manager sections.
     *
     * @return array<int, mixed>
     */
    protected function getFooterWidgets(): array
    {
        return [
            ReservationCalendar::make(['room' => $this->getRecord()]),
        ];
    }

    /**
     * The calendar widget owns its own `record` slot (the clicked reservation),
     * so don't let the page inject this Room into it — the room is passed
     * explicitly via the `room` property above.
     *
     * @return array<string, mixed>
     */
    public function getWidgetData(): array
    {
        return [];
    }
}
