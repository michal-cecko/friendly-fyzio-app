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
 * Sends the visit reminder to confirmed reservations whose visit is now within the
 * reminder window (default 24h). Runs hourly; the `reminder_sent_at` marker makes it
 * send exactly once per reservation.
 */
class SendReservationReminders extends Command
{
    protected $signature = 'reservations:send-reminders';

    protected $description = 'E-mail a visit reminder to confirmed reservations nearing their visit.';

    public function handle(): int
    {
        $now = Carbon::now();
        $cutoff = $now->copy()->addHours(Settings::reminderHours());

        // Coarse DB filter (confirmed, not yet reminded, upcoming date); the exact window
        // is checked in PHP because the visit start combines the date with start_time.
        $candidates = Reservation::query()
            ->where('status', ReservationStatus::Confirmed)
            ->whereNull('reminder_sent_at')
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

            $reservation->client?->notify(new ReservationTemplateNotification($reservation, EmailTemplateKey::ReservationReminder));
            $reservation->update(['reminder_sent_at' => $now]);
            $sent++;
        }

        $this->info("Odesláno {$sent} připomínek.");

        return self::SUCCESS;
    }
}
