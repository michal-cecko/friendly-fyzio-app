<?php

namespace App\Filament\Clusters\Provoz\Resources\Reservations\Actions;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Support\Reservations\ReactivateReservation;
use App\Support\Reservations\ReservationSummary;
use App\Support\Reservations\SlotTakenException;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * Restores a trashed and/or cancelled reservation back to an active state. The
 * confirmation-window rule decides Pending vs auto-Confirmed (mirroring a fresh
 * booking) and the client can be notified with the standard acknowledgement
 * templates — see {@see ReactivateReservation} for the exact semantics. Aborts
 * gracefully when the freed slot is meanwhile occupied by another reservation.
 */
class RestoreReservationAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'restoreReservation';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Obnovit')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->modalHeading('Obnovit')
            ->modalIcon(Heroicon::OutlinedArrowPath)
            ->modalSubmitActionLabel('Obnovit rezervaci')
            ->modalDescription(fn (Reservation $record): HtmlString => ReservationSummary::description($record))
            ->visible(fn (Reservation $record): bool => $record->trashed() || $record->status === ReservationStatus::Cancelled)
            ->schema([
                Toggle::make('notify_client')
                    ->label('Informovat klienta e-mailem')
                    ->helperText('Klient dostane běžné potvrzení rezervace („Přijali jsme vaši rezervaci“). Je-li termín už v potvrzovacím okně, rezervace se rovnou potvrdí a odejde e-mail o automatickém potvrzení.')
                    ->default(true),
            ])
            ->action(function (Reservation $record, array $data): void {
                try {
                    $status = app(ReactivateReservation::class)->handle($record, (bool) ($data['notify_client'] ?? false));
                } catch (SlotTakenException) {
                    Notification::make()
                        ->title('Termín je již obsazen jinou rezervací.')
                        ->body('Rezervaci nelze obnovit — vyberte klientovi nový termín.')
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title($status === ReservationStatus::Confirmed
                        ? 'Rezervace byla obnovena a rovnou potvrzena.'
                        : 'Rezervace byla obnovena a čeká na potvrzení.')
                    ->success()
                    ->send();
            });
    }
}
