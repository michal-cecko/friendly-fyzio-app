<?php

namespace App\Livewire\Zone;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Reservation;
use App\Support\Reservations\ClientReservationActions;
use App\Support\Reservations\ClientReservationState;
use App\Support\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Authenticated reservation detail (pencil frames Profile/Reservation Detail,
 * all seven states). Cancelling runs through the same shared actions as the
 * signed manage link: a free cancel while allowed, otherwise the storno
 * decision (pay / doctor's note / refuse & deactivate).
 */
class ReservationDetail extends Component
{
    #[Locked]
    public string $reservationId = '';

    public bool $confirmingCancel = false;

    /**
     * The post-cancellation result screen to show, if any:
     * null | 'free' | 'storno_paid' | 'doctor_note' | 'deactivated'.
     */
    #[Locked]
    public ?string $confirmation = null;

    public function mount(Reservation $reservation): void
    {
        abort_unless($reservation->client_id === Auth::id(), 404);

        $this->reservationId = $reservation->getKey();
    }

    public function openCancel(): void
    {
        $this->confirmingCancel = true;
    }

    public function closeCancel(): void
    {
        $this->confirmingCancel = false;
    }

    public function cancelFree(ClientReservationActions $actions): void
    {
        $actions->cancelFree($this->reservation());

        $this->finishCancel('free');
    }

    public function cancelAndPay(ClientReservationActions $actions): void
    {
        $actions->cancelAndPay($this->reservation());

        $this->finishCancel('storno_paid');
    }

    public function cancelWithDoctorNote(ClientReservationActions $actions): void
    {
        $actions->cancelWithDoctorNote($this->reservation());

        $this->finishCancel('doctor_note');
    }

    public function cancelAndDeactivate(ClientReservationActions $actions): void
    {
        $actions->cancelAndDeactivate($this->reservation());

        // The account is now deactivated; we intentionally do NOT log out here so
        // the client sees the confirmation screen. EnsureZoneCustomer boots them
        // the moment they navigate anywhere else in the zone.
        $this->finishCancel('deactivated');
    }

    protected function finishCancel(string $confirmation): void
    {
        $this->confirmingCancel = false;
        $this->confirmation = $confirmation;
    }

    protected function reservation(): Reservation
    {
        return Reservation::query()
            ->whereKey($this->reservationId)
            ->where('client_id', Auth::id())
            ->with(['service.cancellationRule', 'therapist.user', 'room', 'payments'])
            ->firstOrFail();
    }

    public function render(): View
    {
        $reservation = $this->reservation();

        return view('livewire.zone.reservation-detail', [
            'reservation' => $reservation,
            'state' => ClientReservationState::for($reservation),
            'openQrPayment' => $reservation->payments
                ->first(fn (Payment $payment): bool => $payment->method === PaymentMethod::Qr && $payment->status !== PaymentStatus::Paid),
            'contactEmail' => (string) (Settings::get('web.contact_email') ?? ''),
        ]);
    }
}
