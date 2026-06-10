<?php

namespace App\Filament\Resources\Reservations\Pages;

use App\Filament\Resources\Reservations\Actions\DeleteReservationAction;
use App\Filament\Resources\Reservations\ReservationResource;
use App\Notifications\ReservationNotification;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditReservation extends EditRecord
{
    protected static string $resource = ReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteReservationAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        if ($this->data['notify_client'] ?? false) {
            $this->record->client?->notify(new ReservationNotification($this->record, 'updated'));
        }
    }
}
