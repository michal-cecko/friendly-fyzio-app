<?php

namespace App\Filament\Clusters\Provoz\Resources\Reservations\Pages;

use App\Enums\EmailTemplateKey;
use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Filament\Clusters\Provoz\Resources\Reservations\Schemas\ReservationForm;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Models\Reservation;
use App\Notifications\ReservationTemplateNotification;
use App\Notifications\TherapistReservationTemplateNotification;
use App\Support\Reservations\ReservationChangeSnapshot;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

class EditReservation extends EditRecord
{
    protected static string $resource = ReservationResource::class;

    /**
     * Whether to e-mail the client + therapist about the change (the
     * "Upozornit zákazníka?" toggle — not a model attribute).
     */
    protected bool $notifyClient = false;

    /**
     * Original values captured before the edit is saved, so the change e-mail
     * can show the puvodni_* tokens next to the new ones.
     *
     * @var array<string, string>
     */
    protected array $reservationChangeSnapshot = [];

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            ActivityLogAction::make(),
        ];
    }

    /**
     * The edit form intentionally omits the PresenceBanner (filament-gaze) that
     * the shared resource form carries — the gaze banner errors on this record —
     * and appends the "notify customer" toggle.
     */
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            ...ReservationForm::components(),
            ReservationForm::notifyClientToggle(),
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return ReservationResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->notifyClient = (bool) ($data['notify_client'] ?? false);
        $this->reservationChangeSnapshot = ReservationChangeSnapshot::capture($this->getRecord());

        unset($data['notify_client']);

        return $data;
    }

    protected function afterSave(): void
    {
        if (! $this->notifyClient) {
            return;
        }

        /** @var Reservation $record */
        $record = $this->getRecord();

        $record->client?->notify(new ReservationTemplateNotification(
            $record,
            EmailTemplateKey::ReservationChanged,
            $this->reservationChangeSnapshot,
        ));
        $record->therapist?->user?->notify(new TherapistReservationTemplateNotification(
            $record,
            EmailTemplateKey::TherapistReservationChanged,
            $this->reservationChangeSnapshot,
        ));
    }
}
