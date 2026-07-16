<?php

namespace App\Filament\Clusters\Provoz\Resources\Reservations\Actions;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Reverts a confirmed reservation back to pending, clearing the confirmation record
 * (who/when). Leaves `confirmation_sent_at` untouched so the automatic confirmation
 * request isn't unexpectedly re-sent — staff can resend manually if needed.
 */
class UnconfirmReservationAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'unconfirmReservation';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Zrušit potvrzení')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Zrušit potvrzení rezervace')
            ->modalDescription('Rezervace se vrátí do stavu „Čeká na potvrzení".')
            ->modalSubmitActionLabel('Zrušit potvrzení')
            ->visible(fn (Reservation $record): bool => $record->status === ReservationStatus::Confirmed
                && ! $record->trashed())
            ->action(function (Reservation $record): void {
                $record->update([
                    'status' => ReservationStatus::Pending,
                    'confirmed_at' => null,
                    'confirmed_by' => null,
                    'confirmed_by_id' => null,
                ]);

                Notification::make()
                    ->title('Potvrzení bylo zrušeno.')
                    ->success()
                    ->send();
            });
    }
}
