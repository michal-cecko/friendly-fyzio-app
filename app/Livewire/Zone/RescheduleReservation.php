<?php

namespace App\Livewire\Zone;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Support\Reservations\RescheduleReservation as RescheduleAction;
use App\Support\Reservations\ReservationSlots;
use App\Support\Reservations\Slot;
use App\Support\Reservations\SlotCalendar;
use App\Support\Reservations\SlotTakenException;
use App\Support\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Přesunout termín": pick another slot for the same service with the same
 * therapist. Only offered while the reservation is still outside its storno
 * window — closer than that the client has to call the clinic (the detail page
 * shows the disabled-state modal instead).
 */
class RescheduleReservation extends Component
{
    #[Locked]
    public string $reservationId = '';

    public ?string $date = null;

    public ?string $startTime = null;

    public bool $slotTaken = false;

    public function mount(Reservation $reservation): void
    {
        abort_unless($reservation->client_id === Auth::id(), 404);

        $this->reservationId = $reservation->getKey();
    }

    public function selectDate(string $date): void
    {
        $this->date = $date;
        $this->startTime = null;
        $this->slotTaken = false;
    }

    public function selectTime(string $time): void
    {
        $this->startTime = $time;
        $this->slotTaken = false;
    }

    public function reschedule(RescheduleAction $reschedule)
    {
        $reservation = $this->reservation();

        if (! $this->isReschedulable($reservation) || blank($this->date) || blank($this->startTime)) {
            return null;
        }

        try {
            $reschedule($reservation, $this->date, $this->startTime);
        } catch (SlotTakenException) {
            $this->slotTaken = true;
            $this->startTime = null;
            unset($this->calendarDays, $this->availableTimes);

            return null;
        }

        session()->flash('status', 'Termín byl přesunut. Potvrzení jsme vám poslali e-mailem.');

        return redirect()->route('zone.reservations.show', $reservation);
    }

    protected function reservation(): Reservation
    {
        return Reservation::query()
            ->whereKey($this->reservationId)
            ->where('client_id', Auth::id())
            ->with(['service.cancellationRule', 'therapist.user'])
            ->firstOrFail();
    }

    /**
     * Moving is allowed for an active reservation that is still outside its
     * free-cancellation window — the same cutoff that governs self-cancelling.
     */
    protected function isReschedulable(Reservation $reservation): bool
    {
        return in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true)
            && ! $reservation->withinStornoWindow();
    }

    /**
     * @return array{available: array<int, string>, full: array<int, string>}
     */
    #[Computed]
    public function calendarDays(): array
    {
        $reservation = $this->reservation();

        if ($reservation->service === null) {
            return ['available' => [], 'full' => []];
        }

        return app(ReservationSlots::class)->dayAvailability(
            $reservation->service,
            Carbon::today(),
            Carbon::today()->addDays(Settings::bookingWindowDays()),
            (string) $reservation->therapist_id,
        );
    }

    /**
     * @return array<int, Slot>
     */
    #[Computed]
    public function availableTimes(): array
    {
        $reservation = $this->reservation();

        if ($reservation->service === null || blank($this->date)) {
            return [];
        }

        return app(ReservationSlots::class)->availableTimes(
            $reservation->service,
            Carbon::parse($this->date),
            (string) $reservation->therapist_id,
        );
    }

    public function render(): View
    {
        $reservation = $this->reservation();
        $first = Carbon::today()->startOfMonth();
        $last = Carbon::today()->addDays(Settings::bookingWindowDays())->startOfMonth();

        return view('livewire.zone.reschedule-reservation', [
            'reservation' => $reservation,
            'reschedulable' => $this->isReschedulable($reservation),
            'months' => SlotCalendar::months($first, $last, $this->calendarDays),
            'initialMonth' => SlotCalendar::initialIndex($first, $this->calendarDays['available']),
            'times' => $this->availableTimes,
            'phone' => Settings::get('web.contact_phone'),
        ]);
    }
}
