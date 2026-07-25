<?php

namespace App\Filament\Clusters\Provoz\Resources\Reservations\Actions;

use App\Filament\Clusters\Provoz\Resources\Reservations\Schemas\ReservationForm;
use App\Models\Reservation;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;

/**
 * Books a reservation for a given client, straight from the client's own page.
 * The client comes from {@see client()} rather than the action's record, so the
 * modal's schema still resolves against the reservation model; the form's client
 * select is pre-filled and locked ({@see ReservationForm::components()}).
 *
 * Broadcasts {@see self::CREATED} afterwards so a client page's reservations
 * relation manager picks the new row up — the modal can be opened from the page
 * header, which would otherwise leave the child table stale.
 */
class CreateReservationAction extends Action
{
    /** Livewire event dispatched once a reservation has been booked. */
    public const CREATED = 'reservation-created';

    protected User|Closure|null $client = null;

    public static function getDefaultName(): ?string
    {
        return 'createReservation';
    }

    /**
     * The client the reservation is booked for.
     */
    public function client(User|Closure $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getClient(): User
    {
        return $this->evaluate($this->client);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Vytvořit rezervaci')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->model(Reservation::class)
            ->modalHeading('Nová rezervace')
            ->modalIcon(Heroicon::OutlinedCalendarDays)
            ->modalSubmitActionLabel('Vytvořit rezervaci')
            ->modalWidth(Width::FourExtraLarge)
            ->schema(ReservationForm::components(lockClient: true))
            ->fillForm(fn (): array => ['client_id' => $this->getClient()->getKey()])
            ->action(function (array $data, Component $livewire): void {
                // client_id is locked (and therefore not dehydrated) — the
                // relation is what decides whose reservation this is.
                unset($data['client_id']);

                /** @var Reservation $reservation */
                $reservation = $this->getClient()->reservations()->create($data);

                $livewire->dispatch(self::CREATED, reservation: $reservation->getKey());

                Notification::make()
                    ->title('Rezervace byla vytvořena.')
                    ->success()
                    ->send();
            });
    }
}
