<?php

namespace App\Filament\Clusters\Provoz\Resources\Reservations\Actions;

use App\Models\Reservation;
use App\Notifications\ReservationNotification;
use App\Support\Reservations\ReservationSummary;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\HtmlString;

class DeleteReservationAction extends DeleteAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->modalHeading('Smazat rezervaci?')
            ->modalDescription(fn (Reservation $record): HtmlString => ReservationSummary::description($record))
            ->modalSubmitActionLabel('Smazat')
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
