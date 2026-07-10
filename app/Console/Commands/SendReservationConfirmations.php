<?php

namespace App\Console\Commands;

use App\Enums\EmailTemplateKey;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Notifications\ReservationTemplateNotification;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Sends the customer confirmation-request e-mail for pending reservations whose visit
 * is now within the confirmation window (default 48h). Runs hourly; the
 * `confirmation_sent_at` marker makes it send exactly once per reservation.
 */
class SendReservationConfirmations extends Command
{
    protected $signature = 'reservations:send-confirmations';

    protected $description = 'E-mail customers a confirmation request for pending reservations nearing their visit.';

    public function handle(): int
    {
        $now = Carbon::now();
        $cutoff = $now->copy()->addHours(Settings::confirmationHours());

        // Coarse DB filter (pending, not yet asked, upcoming date); the exact window is
        // checked in PHP because the visit start combines the date with start_time.
        $candidates = Reservation::query()
            ->where('status', ReservationStatus::Pending)
            ->whereNull('confirmation_sent_at')
            ->whereDate('reservation_date', '>=', $now->toDateString())
            ->whereDate('reservation_date', '<=', $cutoff->toDateString())
            ->with('client')
            ->get();

        $sent = 0;

        foreach ($candidates as $reservation) {
            $startsAt = $reservation->startsAt();

            if ($startsAt->lessThanOrEqualTo($now) || $startsAt->greaterThan($cutoff)) {
                continue;
            }

            $reservation->client?->notify(new ReservationTemplateNotification($reservation, EmailTemplateKey::ReservationPending));
            $reservation->update(['confirmation_sent_at' => $now]);
            $sent++;
        }

        $this->info("Odesláno {$sent} žádostí o potvrzení.");

        return self::SUCCESS;
    }
}
