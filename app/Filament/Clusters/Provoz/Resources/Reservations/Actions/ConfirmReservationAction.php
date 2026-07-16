<?php

namespace App\Filament\Clusters\Provoz\Resources\Reservations\Actions;

use App\Enums\ConfirmationSource;
use App\Enums\EmailTemplateKey;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Notifications\ReservationTemplateNotification;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Staff confirmation of a pending reservation on the customer's behalf (e.g. the
 * customer told the therapist they'll attend). Records the therapist source + the
 * acting user and, optionally, e-mails the customer the confirmation.
 */
class ConfirmReservationAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'confirmReservation';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Potvrdit rezervaci')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->modalHeading('Potvrdit rezervaci')
            ->modalIcon(Heroicon::OutlinedCheckCircle)
            ->modalSubmitActionLabel('Potvrdit')
            ->visible(fn (Reservation $record): bool => $record->status === ReservationStatus::Pending
                && ! $record->trashed())
            ->schema([
                Toggle::make('notify_client')
                    ->label('Informovat klienta e-mailem')
                    ->default(true),
            ])
            ->action(function (Reservation $record, array $data): void {
                $record->update([
                    'status' => ReservationStatus::Confirmed,
                    'confirmed_at' => now(),
                    'confirmed_by' => ConfirmationSource::Therapist,
                    'confirmed_by_id' => auth()->id(),
                ]);

                if ($data['notify_client'] ?? false) {
                    $record->client?->notify(new ReservationTemplateNotification($record, EmailTemplateKey::ReservationConfirmed));
                }

                Notification::make()
                    ->title('Rezervace byla potvrzena.')
                    ->success()
                    ->send();
            });
    }
}
