<?php

namespace App\Filament\Clusters\Provoz\Resources\Reservations\Actions;

use App\Enums\EmailTemplateKey;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Notifications\ReservationTemplateNotification;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Cancels a reservation (status -> Cancelled) with a reason and, by default, e-mails
 * the client the CMS cancellation notice. This is the therapist/admin counterpart to
 * the customer self-cancel magic link; unlike delete, the record is kept (shown as
 * „Storno" in the calendar).
 */
class CancelReservationAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'cancelReservation';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Zrušit rezervaci')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->modalHeading('Zrušit rezervaci')
            ->modalIcon(Heroicon::OutlinedXCircle)
            ->modalDescription('Rezervace bude označena jako stornovaná. Klientovi můžete odeslat e-mail s oznámením o zrušení.')
            ->modalSubmitActionLabel('Zrušit rezervaci')
            ->visible(fn (Reservation $record): bool => $record->status !== ReservationStatus::Cancelled)
            ->schema([
                Textarea::make('cancellation_reason')
                    ->label('Důvod zrušení')
                    ->rows(2)
                    ->required(),
                Toggle::make('notify_client')
                    ->label('Informovat klienta e-mailem')
                    ->default(true),
            ])
            ->action(function (Reservation $record, array $data): void {
                $record->update([
                    'status' => ReservationStatus::Cancelled,
                    'cancellation_reason' => $data['cancellation_reason'],
                ]);

                if ($data['notify_client'] ?? false) {
                    $record->client?->notify(new ReservationTemplateNotification($record, EmailTemplateKey::ReservationCancelled));
                }

                Notification::make()
                    ->title('Rezervace byla zrušena.')
                    ->success()
                    ->send();
            });
    }
}
