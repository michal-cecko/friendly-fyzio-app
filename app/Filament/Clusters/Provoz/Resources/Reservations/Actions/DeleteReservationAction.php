<?php

namespace App\Filament\Clusters\Provoz\Resources\Reservations\Actions;

use App\Models\Reservation;
use App\Notifications\ReservationNotification;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Toggle;

class DeleteReservationAction extends DeleteAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->schema([
                Toggle::make('notify_client')
                    ->label('Informovat klienta e-mailem')
                    ->default(false),
            ])
            ->before(function (Reservation $record, array $data): void {
                if ($data['notify_client'] ?? false) {
                    $record->client?->notify(new ReservationNotification($record, 'cancelled'));
                }
            });
    }
}
