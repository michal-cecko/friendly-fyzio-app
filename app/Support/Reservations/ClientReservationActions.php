<?php

namespace App\Support\Reservations;

use App\Enums\Capability;
use App\Enums\ConfirmationSource;
use App\Enums\EmailTemplateKey;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Http\Controllers\ReservationManageController;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\ReservationStornoPaymentNotification;
use App\Notifications\ReservationTemplateNotification;
use App\Notifications\TherapistReservationTemplateNotification;
use App\Support\ActivityLog\LogActivity;
use Filament\Notifications\Notification;

/**
 * Every customer-initiated reservation action, shared by the two client
 * surfaces: the passwordless signed manage link
 * ({@see ReservationManageController}) and the
 * authenticated client zone. A free self-cancel is available only while the
 * reservation does not require the storno decision
 * ({@see Reservation::requiresStornoDecision()}); afterwards the customer must
 * pick one of the three storno resolutions (pay / doctor's note / deactivate).
 */
class ClientReservationActions
{
    /**
     * Confirm a pending reservation. Guarded by the Pending check so a double-submit
     * (already Confirmed) never re-sends the confirmation e-mail.
     */
    public function confirm(Reservation $reservation): void
    {
        if ($reservation->status !== ReservationStatus::Pending) {
            return;
        }

        $reservation->update([
            'status' => ReservationStatus::Confirmed,
            'confirmed_at' => now(),
            'confirmed_by' => ConfirmationSource::Customer,
            'confirmed_by_id' => $reservation->client_id,
        ]);

        $reservation->client?->notify(new ReservationTemplateNotification($reservation, EmailTemplateKey::ReservationConfirmed));
        $reservation->therapist?->user?->notify(new TherapistReservationTemplateNotification($reservation, EmailTemplateKey::TherapistReservationConfirmed));

        LogActivity::record('reservation_confirmed', $reservation, 'Rezervace potvrzena', [
            'source' => 'Zákazník (online)',
            'notified_client' => true,
            'notified_therapist' => $reservation->therapist?->user !== null,
        ], $reservation->client);
    }

    /**
     * Free cancellation — allowed for an active reservation that does not (yet) require
     * the storno decision: still Pending and outside the storno window, or carrying no
     * fee. Otherwise the customer must resolve the storno decision instead.
     */
    public function cancelFree(Reservation $reservation): void
    {
        $isActive = in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true);

        if (! $isActive || $reservation->requiresStornoDecision()) {
            return;
        }

        $reservation->update([
            'status' => ReservationStatus::Cancelled,
            'cancellation_reason' => 'Zrušeno klientem',
        ]);

        $reservation->client?->notify(new ReservationTemplateNotification($reservation, EmailTemplateKey::ReservationCancelled));
        $this->notifyTherapistOfClientCancellation($reservation, 'Zrušeno v řádné lhůtě – bez storno poplatku', '');

        LogActivity::record('reservation_cancelled', $reservation, 'Rezervace zrušena', [
            'source' => 'Zákazník (online) – v řádné lhůtě',
            'notified_client' => true,
            'notified_therapist' => $reservation->therapist?->user !== null,
        ], $reservation->client);
    }

    /**
     * Storno cancel, "I won't come but I'll pay": cancel, raise an unpaid storno Payment
     * (Czech QR-Platba), and e-mail the customer the payment instructions + QR.
     */
    public function cancelAndPay(Reservation $reservation): ?Payment
    {
        if (! $reservation->requiresStornoDecision()) {
            return null;
        }

        $reservation->update([
            'status' => ReservationStatus::Cancelled,
            'cancellation_reason' => 'Pozdní storno – klient zaplatí',
            'payment_status' => PaymentStatus::Unpaid,
        ]);

        /** @var Payment $payment */
        $payment = $reservation->payments()->create([
            'client_id' => $reservation->client_id,
            'amount' => $reservation->stornoFee(),
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Unpaid,
        ]);

        $reservation->client?->notify(new ReservationStornoPaymentNotification($reservation, $payment));
        $this->notifyTherapistOfClientCancellation($reservation, 'Klient uhradí storno poplatek', $payment->amount.' Kč');

        LogActivity::record('reservation_storno_charged', $reservation, 'Storno poplatek vyžádán', [
            'source' => 'Zákazník (online)',
            'fee' => $payment->amount.' Kč',
            'notified_client' => true,
            'notified_therapist' => $reservation->therapist?->user !== null,
        ], $reservation->client);

        return $payment;
    }

    /**
     * Storno cancel, "I was ill and will supply a doctor's note": cancel, waive the fee
     * pending review, flag the reservation, notify staff, and acknowledge the customer.
     */
    public function cancelWithDoctorNote(Reservation $reservation): void
    {
        if (! $reservation->requiresStornoDecision()) {
            return;
        }

        $reservation->update([
            'status' => ReservationStatus::Cancelled,
            'cancellation_reason' => 'Pozdní storno – potvrzení od lékaře',
            'doctor_note_requested_at' => now(),
        ]);

        $reservation->client?->notify(new ReservationTemplateNotification($reservation, EmailTemplateKey::ReservationDoctorNote));
        $this->notifyTherapistOfClientCancellation($reservation, 'Klient doloží potvrzení od lékaře (poplatek pozastaven)', 'pozastaveno');

        $this->notifyStaffOfDoctorNote($reservation);

        LogActivity::record('reservation_cancelled', $reservation, 'Rezervace zrušena', [
            'source' => 'Zákazník (online) – potvrzení od lékaře',
            'notified_client' => true,
            'notified_therapist' => $reservation->therapist?->user !== null,
        ], $reservation->client);
    }

    /**
     * Storno cancel, "I won't come and won't pay": cancel and fully deactivate the
     * account (blocks login + online booking).
     */
    public function cancelAndDeactivate(Reservation $reservation): void
    {
        if (! $reservation->requiresStornoDecision()) {
            return;
        }

        $reservation->update([
            'status' => ReservationStatus::Cancelled,
            'cancellation_reason' => 'Pozdní storno – bez úhrady',
        ]);

        $reservation->client?->update(['deactivated_at' => now()]);

        $reservation->client?->notify(new ReservationTemplateNotification($reservation, EmailTemplateKey::ReservationCancelled));
        $this->notifyTherapistOfClientCancellation($reservation, 'Klient odmítl úhradu – účet deaktivován', $reservation->stornoFee().' Kč');

        LogActivity::record('reservation_cancelled', $reservation, 'Rezervace zrušena', [
            'source' => 'Zákazník (online) – bez úhrady, účet deaktivován',
            'fee' => $reservation->stornoFee().' Kč',
            'notified_client' => true,
            'notified_therapist' => $reservation->therapist?->user !== null,
        ], $reservation->client);
    }

    /**
     * Tell the therapist that the client cancelled, describing how the storno was
     * resolved. Fired only from the client-initiated cancel paths.
     */
    protected function notifyTherapistOfClientCancellation(Reservation $reservation, string $resolution, string $amount): void
    {
        $reservation->therapist?->user?->notify(new TherapistReservationTemplateNotification(
            $reservation,
            EmailTemplateKey::TherapistReservationCancelled,
            ['storno_reseni' => $resolution, 'storno_castka' => $amount],
        ));
    }

    protected function notifyStaffOfDoctorNote(Reservation $reservation): void
    {
        $admins = User::query()->whereHas('roles', fn ($q) => $q->where('name', Capability::Admin->roleName()))->get();

        if ($admins->isEmpty()) {
            return;
        }

        Notification::make()
            ->title('Storno s potvrzením od lékaře')
            ->body(($reservation->client?->name ?? 'Klient').' zrušil rezervaci a doloží potvrzení od lékaře. Storno poplatek je pozastaven.')
            ->icon('heroicon-o-document-text')
            ->warning()
            ->sendToDatabase($admins);
    }
}
