<?php

namespace App\Filament\Clusters\Provoz\Resources\Reservations\Pages;

use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Notifications\ReservationNotification;
use Filament\Resources\Pages\CreateRecord;

class CreateReservation extends CreateRecord
{
    protected static string $resource = ReservationResource::class;

    protected function afterCreate(): void
    {
        if ($this->data['notify_client'] ?? false) {
            $this->record->client?->notify(new ReservationNotification($this->record, 'created'));
        }
    }
}
