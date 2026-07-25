<?php

namespace App\Filament\Clusters\Provoz\Resources\Reservations\Actions;

use App\Enums\EmailTemplateKey;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Notifications\ReservationTemplateNotification;
use App\Support\ActivityLog\LogActivity;
use App\Support\Emails\SentEmailReceipt;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * The single reservation cancellation action (replaces the old separate Cancel +
 * Delete actions). It always marks the reservation as storn/Cancelled with a
 * reason and, optionally, e-mails the client. The "Úplně vymazat ze systému?"
 * opt-in turns the cancellation into a deletion: the client still receives the
 * ordinary cancellation notice (sent synchronously, before the delete), and the
 * reservation moves to the trash, from where `model:prune` erases it for good
 * after 30 days — payments and invoices survive unlinked (see Reservation::booted()).
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
            ->label('Zrušit')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->modalHeading('Zrušit')
            ->modalIcon(Heroicon::OutlinedTrash)
            ->modalSubmitActionLabel('Zrušit rezervaci')
            ->visible(fn (Reservation $record): bool => ! $record->trashed() && $record->settled_at === null)
            ->schema([
                Textarea::make('cancellation_reason')
                    ->label('Důvod zrušení')
                    ->rows(2)
                    ->required()
                    ->default(fn (Reservation $record): ?string => $record->cancellation_reason),
                Toggle::make('force_delete')
                    ->label('Úplně vymazat ze systému?')
                    ->helperText('Zapnuto: klient dostane běžné oznámení o zrušení a rezervace se přesune do koše — po 30 dnech se ze systému nenávratně vymaže (platby a faktury zůstávají). Vypnuto: zůstane v evidenci jako stornovaná.')
                    ->default(false),
                Toggle::make('notify_client')
                    ->label('Informovat klienta e-mailem')
                    ->default(true),
            ])
            ->action(function (Reservation $record, array $data): void {
                $record->update([
                    'status' => ReservationStatus::Cancelled,
                    'cancellation_reason' => $data['cancellation_reason'],
                ]);

                $notifyClient = ($data['notify_client'] ?? false) && filled($record->client?->email);

                if ($notifyClient) {
                    $record->client?->notify(new ReservationTemplateNotification($record, EmailTemplateKey::ReservationCancelled));

                    SentEmailReceipt::forCurrentUser('Zrušení rezervace');
                }

                $erased = (bool) ($data['force_delete'] ?? false);

                LogActivity::record('reservation_cancelled', $record, 'Rezervace zrušena', [
                    'reason' => $data['cancellation_reason'],
                    'notified_client' => $notifyClient,
                    'erased' => $erased,
                ]);

                if ($erased) {
                    $record->delete();
                }

                Notification::make()
                    ->title($erased ? 'Rezervace byla zrušena a přesunuta do koše.' : 'Rezervace byla zrušena.')
                    ->success()
                    ->send();
            });
    }
}
