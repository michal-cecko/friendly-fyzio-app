<?php

namespace App\Filament\Clusters\Provoz\Resources\Reservations\Pages;

use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Filament\Clusters\Provoz\Resources\Reservations\Schemas\ReservationForm;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Concerns\PromptsScheduleChangeNotification;
use App\Models\Reservation;
use App\Support\ActivityLog\LogActivity;
use App\Support\Reservations\NotifyReservationChange;
use App\Support\Reservations\ReservationChangeSnapshot;
use Filament\Actions\ViewAction;
use Filament\Schemas\Schema;

class EditReservation extends BaseEditRecord
{
    use PromptsScheduleChangeNotification;

    protected static string $resource = ReservationResource::class;

    public function getTitle(): string
    {
        /** @var Reservation $record */
        $record = $this->getRecord();

        return 'Upravit rezervaci '.($record->client?->name ?? 'bez klienta');
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            ActivityLogAction::make(),
        ];
    }

    /**
     * The edit form intentionally omits the PresenceBanner (filament-gaze) that the
     * shared resource form carries — the gaze banner errors on this record. The old
     * "Upozornit zákazníka?" toggle is gone: after saving a termín change the page
     * prompts for the notification instead ({@see PromptsScheduleChangeNotification}).
     */
    public function form(Schema $schema): Schema
    {
        // One column, not Filament's two: the groups are card-contained here and each
        // packs its own fields four-up, so splitting the page in half would only make
        // them narrower.
        return $schema
            ->columns(1)
            ->components(ReservationForm::components(contained: true));
    }

    /**
     * @return array<int, string>
     */
    protected function scheduleAttributes(): array
    {
        return Reservation::SCHEDULE_ATTRIBUTES;
    }

    /**
     * @return array<string, string>
     */
    protected function captureScheduleSnapshot(): array
    {
        return ReservationChangeSnapshot::capture($this->getRecord());
    }

    protected function scheduleChangeAudience(): string
    {
        return 'zákazníka a terapeuta';
    }

    protected function scheduleChangeRedirectUrl(): ?string
    {
        return ReservationResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function afterRecordSaved(): void
    {
        LogActivity::record('reservation_edited', $this->getRecord(), 'Rezervace upravena');
    }

    /**
     * @param  array<string, string>  $snapshot
     */
    protected function sendScheduleChangeNotification(?string $reason, array $snapshot): int
    {
        return app(NotifyReservationChange::class)($this->getRecord(), $snapshot, $reason);
    }
}
