<?php

namespace App\Support\Reservations;

use App\Enums\ConfirmationSource;
use App\Enums\EmailTemplateKey;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Notifications\ReservationTemplateNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Brings a cancelled and/or trashed reservation back to life: undeletes it, clears
 * the cancellation, re-arms the confirmation/reminder e-mail markers, and applies
 * the same confirmation-window rule as a fresh booking — inside the window the
 * visit is auto-confirmed, otherwise it waits for the customer again (the standard
 * confirmation-request e-mail re-fires thanks to the cleared marker). The client
 * can be notified with the standard acknowledgement templates; no restore-specific
 * template exists on purpose. Open payments are deliberately left untouched.
 */
class ReactivateReservation
{
    /**
     * @throws SlotTakenException when the freed slot is meanwhile occupied by
     *                            another active reservation (the pre-check and the
     *                            unique-index race are both mapped to it)
     */
    public function handle(Reservation $reservation, bool $notifyClient): ReservationStatus
    {
        if ($this->slotOccupiedByAnother($reservation)) {
            throw new SlotTakenException;
        }

        $confirmed = $reservation->withinConfirmationWindow();

        try {
            DB::transaction(function () use ($reservation, $confirmed): void {
                if ($reservation->trashed()) {
                    $reservation->restore();
                }

                $reservation->update([
                    'status' => $confirmed ? ReservationStatus::Confirmed : ReservationStatus::Pending,
                    'confirmed_at' => $confirmed ? now() : null,
                    'confirmed_by' => $confirmed ? ConfirmationSource::Automatic : null,
                    'confirmed_by_id' => null,
                    'cancellation_reason' => null,
                    'confirmation_sent_at' => null,
                    'reminder_sent_at' => null,
                    // The reservation is active again — reset the "handled" markers.
                    'settled_at' => null,
                    'doctor_note_requested_at' => null,
                    'doctor_note_resolved_at' => null,
                ]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw new SlotTakenException(previous: $exception);
        }

        if ($notifyClient) {
            $reservation->client?->notify(new ReservationTemplateNotification(
                $reservation,
                $confirmed ? EmailTemplateKey::ReservationAutoConfirmed : EmailTemplateKey::ReservationCreated,
            ));
        }

        return $reservation->status;
    }

    protected function slotOccupiedByAnother(Reservation $reservation): bool
    {
        return Reservation::query()
            ->whereKeyNot($reservation->getKey())
            ->where('therapist_id', $reservation->therapist_id)
            ->whereDate('reservation_date', $reservation->reservation_date)
            ->where('start_time', $reservation->start_time)
            ->where('status', '!=', ReservationStatus::Cancelled)
            ->exists();
    }
}
