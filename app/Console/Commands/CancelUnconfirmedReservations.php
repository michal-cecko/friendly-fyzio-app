<?php

namespace App\Console\Commands;

use App\Enums\EmailTemplateKey;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Notifications\ReservationTemplateNotification;
use App\Notifications\TherapistReservationAutoCancelledNotification;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Cancels reservations still unconfirmed by the cutoff (default 24h before the visit)
 * and e-mails the customer the automatic-cancellation notice. Runs hourly; the
 * Pending -> Cancelled transition is itself the idempotency guard.
 *
 * Last-minute bookings (created inside the confirmation window) are exempt so they are
 * never cancelled before the customer had a fair chance to confirm.
 */
class CancelUnconfirmedReservations extends Command
{
    protected $signature = 'reservations:cancel-unconfirmed';

    protected $description = 'Cancel reservations not confirmed by the cutoff and notify the customer.';

    public function handle(): int
    {
        $now = Carbon::now();
        $cutoff = $now->copy()->addHours(Settings::autoCancelHours());
        $confirmationHours = Settings::confirmationHours();

        $candidates = Reservation::query()
            ->where('status', ReservationStatus::Pending)
            ->whereDate('reservation_date', '>=', $now->toDateString())
            ->whereDate('reservation_date', '<=', $cutoff->toDateString())
            ->with(['client', 'therapist.user'])
            ->get();

        $cancelled = 0;

        foreach ($candidates as $reservation) {
            $startsAt = $reservation->startsAt();

            if ($startsAt->lessThanOrEqualTo($now) || $startsAt->greaterThan($cutoff)) {
                continue;
            }

            // Lead-time guard: only cancel reservations booked with the full confirmation
            // window ahead of them, so a last-minute booking isn't cancelled instantly.
            if ($reservation->created_at->greaterThan($startsAt->copy()->subHours($confirmationHours))) {
                continue;
            }

            $reservation->update([
                'status' => ReservationStatus::Cancelled,
                'cancellation_reason' => 'Automatické zrušení – nepotvrzená účast',
            ]);

            $reservation->client?->notify(new ReservationTemplateNotification($reservation, EmailTemplateKey::ReservationAutoCancelled));
            $reservation->therapist?->user?->notify(new TherapistReservationAutoCancelledNotification($reservation));
            $cancelled++;
        }

        $this->info("Automaticky zrušeno {$cancelled} rezervací.");

        return self::SUCCESS;
    }
}
