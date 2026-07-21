<?php

namespace App\Filament\Clusters\Provoz\Resources\ReservationDayWaitlist\Pages;

use App\Filament\Clusters\Provoz\Resources\ReservationDayWaitlist\ReservationDayWaitlistResource;
use Filament\Resources\Pages\ListRecords;

class ListReservationDayWaitlistEntries extends ListRecords
{
    protected static string $resource = ReservationDayWaitlistResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
