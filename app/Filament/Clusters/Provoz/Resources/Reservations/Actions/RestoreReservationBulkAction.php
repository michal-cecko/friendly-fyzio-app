<?php

namespace App\Filament\Clusters\Provoz\Resources\Reservations\Actions;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Support\Reservations\ReactivateReservation;
use App\Support\Reservations\SlotTakenException;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * Bulk counterpart of {@see RestoreReservationAction}: reactivates every selected
 * trashed/cancelled reservation via {@see ReactivateReservation}, skipping the
 * ones that are already active or whose slot is meanwhile occupied.
 */
class RestoreReservationBulkAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'restoreReservations';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Obnovit rezervace')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->modalHeading('Obnovit vybrané rezervace')
            ->modalIcon(Heroicon::OutlinedArrowPath)
            ->modalSubmitActionLabel('Obnovit rezervace')
            ->schema([
                Toggle::make('notify_client')
                    ->label('Informovat klienty e-mailem')
                    ->helperText('Klienti dostanou běžné potvrzení rezervace; termíny už v potvrzovacím okně se rovnou potvrdí s e-mailem o automatickém potvrzení.')
                    ->default(true),
            ])
            ->action(function (Collection $records, array $data): void {
                $restored = 0;
                $skipped = 0;

                $records->each(function (Reservation $record) use ($data, &$restored, &$skipped): void {
                    if (! $record->trashed() && $record->status !== ReservationStatus::Cancelled) {
                        $skipped++;

                        return;
                    }

                    try {
                        app(ReactivateReservation::class)->handle($record, (bool) ($data['notify_client'] ?? false));
                        $restored++;
                    } catch (SlotTakenException) {
                        $skipped++;
                    }
                });

                Notification::make()
                    ->title("Obnovené rezervace: {$restored}.")
                    ->body($skipped > 0 ? "Přeskočeno: {$skipped} (aktivní, nebo už obsazený termín)." : null)
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
