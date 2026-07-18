<?php

namespace App\Support\Reservations;

use App\Enums\EmailTemplateKey;
use App\Models\Reservation;
use App\Notifications\ReservationTemplateNotification;
use App\Notifications\TherapistReservationTemplateNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Moves an existing reservation to another slot of the same service with the
 * same therapist — the client-zone counterpart of the admin calendar's drag &
 * drop. Serialised with the same per-therapist/date cache lock as new bookings
 * and re-resolves the target slot inside the transaction, so a slot taken in
 * the meantime is rejected with a {@see SlotTakenException}. The status is left
 * untouched (a pending reservation stays pending); both parties get the same
 * "reservation changed" e-mails the admin move sends.
 */
class RescheduleReservation
{
    public function __construct(protected ReservationSlots $slots) {}

    public function __invoke(Reservation $reservation, string $date, string $startTime): Reservation
    {
        $lock = Cache::lock("reservation:{$reservation->therapist_id}:{$date}", 10);

        /** @var array{0: Reservation, 1: array<string, string>} $result */
        $result = $lock->block(5, fn (): array => DB::transaction(fn (): array => $this->persist($reservation, $date, $startTime)));

        [$reservation, $snapshot] = $result;

        $reservation->client?->notify(new ReservationTemplateNotification(
            $reservation,
            EmailTemplateKey::ReservationChanged,
            $snapshot,
        ));
        $reservation->therapist?->user?->notify(new TherapistReservationTemplateNotification(
            $reservation,
            EmailTemplateKey::TherapistReservationChanged,
            $snapshot,
        ));

        return $reservation;
    }

    /**
     * @return array{0: Reservation, 1: array<string, string>}
     */
    protected function persist(Reservation $reservation, string $date, string $startTime): array
    {
        $service = $reservation->service;

        if ($service === null) {
            throw new SlotTakenException;
        }

        $slot = $this->slots->resolveSlot($service, Carbon::parse($date), $startTime, (string) $reservation->therapist_id);

        if ($slot === null) {
            throw new SlotTakenException;
        }

        // Snapshot the stored state before the update so the e-mails can show
        // the original termin next to the new one.
        $snapshot = ReservationChangeSnapshot::capture($reservation);

        $reservation->update([
            'reservation_date' => $slot->date,
            'start_time' => $slot->start().':00',
            'end_time' => $slot->end().':00',
            'room_id' => $slot->roomId,
        ]);

        return [$reservation->fresh(['service', 'therapist.user', 'client']), $snapshot];
    }
}
