<?php

namespace App\Filament\Clusters\Provoz\Resources\Reservations\Actions;

use App\Enums\EmailTemplateKey;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Notifications\ReservationTemplateNotification;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * Bulk counterpart of {@see CancelReservationAction}: cancels every selected
 * reservation with one shared reason, optionally e-mailing each client and
 * optionally moving them to the trash, from where `model:prune` erases them
 * for good after 30 days.
 */
class CancelReservationBulkAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'cancelReservations';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Zrušit rezervace')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->modalHeading('Zrušit vybrané rezervace')
            ->modalIcon(Heroicon::OutlinedTrash)
            ->modalSubmitActionLabel('Zrušit rezervace')
            ->schema([
                Textarea::make('cancellation_reason')
                    ->label('Důvod zrušení')
                    ->rows(2)
                    ->required(),
                Toggle::make('force_delete')
                    ->label('Úplně vymazat ze systému?')
                    ->helperText('Zapnuto: klienti dostanou běžné oznámení o zrušení a rezervace se přesunou do koše — po 30 dnech se ze systému nenávratně vymažou (platby a faktury zůstávají). Vypnuto: zůstanou v evidenci jako stornované.')
                    ->default(false),
                Toggle::make('notify_client')
                    ->label('Informovat klienty e-mailem')
                    ->default(true),
            ])
            ->action(function (Collection $records, array $data): void {
                $records->each(function (Reservation $record) use ($data): void {
                    if ($record->trashed()) {
                        return;
                    }

                    $record->update([
                        'status' => ReservationStatus::Cancelled,
                        'cancellation_reason' => $data['cancellation_reason'],
                    ]);

                    if ($data['notify_client'] ?? false) {
                        $record->client?->notify(new ReservationTemplateNotification($record, EmailTemplateKey::ReservationCancelled));
                    }

                    if ($data['force_delete'] ?? false) {
                        $record->delete();
                    }
                });

                Notification::make()
                    ->title('Vybrané rezervace byly zrušeny.')
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
