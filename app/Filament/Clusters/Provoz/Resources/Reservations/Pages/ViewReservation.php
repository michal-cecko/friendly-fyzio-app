<?php

namespace App\Filament\Clusters\Provoz\Resources\Reservations\Pages;

use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Filament\Clusters\Provoz\Resources\Reservations\Schemas\ReservationForm;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewReservation extends ViewRecord
{
    protected static string $resource = ReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->schema(ReservationForm::components()),
        ];
    }
}
