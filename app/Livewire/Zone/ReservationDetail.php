<?php

namespace App\Livewire\Zone;

use App\Enums\PaymentMethod;
use App\Enums\ReservationDocumentType;
use App\Models\Payment;
use App\Models\Reservation;
use App\Support\Clients\DeactivateAccount;
use App\Support\Reservations\ClientReservationActions;
use App\Support\Reservations\ClientReservationState;
use App\Support\Reservations\ReservationDocuments;
use App\Support\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Authenticated reservation detail (pencil frames Profile/Reservation Detail,
 * all seven states). Cancelling runs through the same shared actions as the
 * signed manage link: a free cancel while allowed, otherwise the storno
 * decision (pay / doctor's note / refuse & deactivate).
 */
class ReservationDetail extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $reservationId = '';

    /**
     * Doctor's notes staged for upload (PDF or a photo of the note).
     *
     * @var array<int, TemporaryUploadedFile>
     */
    public array $doctorNoteFiles = [];

    public bool $confirmingCancel = false;

    public bool $confirmingConfirm = false;

    /** Whether the "change how the storno is resolved" modal is open. */
    public bool $changingStorno = false;

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

    public function openConfirm(): void
    {
        $this->confirmingConfirm = true;
    }

    public function closeConfirm(): void
    {
        $this->confirmingConfirm = false;
    }

    /**
     * Confirm a pending reservation. Stays on the detail page — the flipped badge
     * and the "Potvrzeno" timestamp are the feedback, no result screen.
     */
    public function confirmReservation(ClientReservationActions $actions): void
    {
        $actions->confirm($this->reservation());

        $this->confirmingConfirm = false;
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

    /**
     * Dismiss the post-cancellation result screen and show the reservation detail —
     * where the doctor's note is actually uploaded.
     */
    public function showDetail(): void
    {
        $this->confirmation = null;
    }

    public function openChangeStorno(): void
    {
        $this->changingStorno = true;
    }

    public function closeChangeStorno(): void
    {
        $this->changingStorno = false;
    }

    /**
     * "I can't get the note after all — I'll pay." Raises the storno fee and closes
     * the pending note.
     */
    public function switchToStornoPayment(ClientReservationActions $actions): void
    {
        $actions->switchToStornoPayment($this->reservation());

        $this->changingStorno = false;
    }

    /**
     * The mirror switch: from "I'll pay" back to promising a doctor's note.
     */
    public function switchToDoctorNote(ClientReservationActions $actions): void
    {
        $actions->switchToDoctorNote($this->reservation());

        $this->changingStorno = false;
    }

    /**
     * "I won't pay at all." Deactivates (and blacklists) the account — the one
     * resolution that cannot be changed again.
     */
    public function switchToDeactivation(ClientReservationActions $actions): void
    {
        $actions->switchToDeactivation($this->reservation());

        $this->changingStorno = false;
        $this->confirmation = 'deactivated';
    }

    /**
     * Attach the staged doctor's notes. Staff are notified per file; the storno fee
     * stays suspended until they resolve it either way.
     */
    public function uploadDoctorNote(ReservationDocuments $documents): void
    {
        $reservation = $this->reservation();

        if (! $documents->canUpload($reservation)) {
            return;
        }

        $this->validate([
            'doctorNoteFiles' => ['required', 'array', 'max:5'],
            'doctorNoteFiles.*' => $documents->rules(),
        ], attributes: ['doctorNoteFiles.*' => 'potvrzení']);

        foreach ($this->doctorNoteFiles as $file) {
            $documents->store($reservation, $file, ReservationDocumentType::DoctorNote, $reservation->client);
        }

        $this->doctorNoteFiles = [];

        session()->flash('doctor_note_status', 'Děkujeme, potvrzení jsme přijali. Ozveme se vám, jakmile jej zkontrolujeme.');
    }

    /**
     * Remove a note uploaded by mistake — allowed only until staff resolve the storno.
     */
    public function removeDoctorNote(string $documentId, ReservationDocuments $documents): void
    {
        $document = $this->reservation()
            ->documents()
            ->whereKey($documentId)
            ->first();

        if ($document !== null) {
            $documents->delete($document);
        }
    }

    protected function reservation(): Reservation
    {
        return Reservation::query()
            ->whereKey($this->reservationId)
            ->where('client_id', Auth::id())
            ->with(['service.cancellationRule', 'therapist.user', 'room', 'payments', 'doctorNoteDocuments'])
            ->firstOrFail();
    }

    public function render(): View
    {
        $reservation = $this->reservation();

        return view('livewire.zone.reservation-detail', [
            'reservation' => $reservation,
            'state' => ClientReservationState::for($reservation),
            'doctorNoteDocuments' => $reservation->doctorNoteDocuments->sortByDesc('created_at'),
            // What refusing to pay would take down with the account, named in the
            // confirmation step so the client is not surprised by the cascade.
            'deactivationPreview' => $reservation->client !== null
                ? app(DeactivateAccount::class)->previewSentence($reservation->client)
                : null,
            'openQrPayment' => $reservation->payments
                ->first(fn (Payment $payment): bool => $payment->method === PaymentMethod::Qr && $payment->status->isOpen()),
            'contactEmail' => (string) (Settings::get('web.contact_email') ?? ''),
        ]);
    }
}
