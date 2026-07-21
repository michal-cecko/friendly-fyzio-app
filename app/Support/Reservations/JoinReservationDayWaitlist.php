<?php

namespace App\Support\Reservations;

use App\Enums\EmailTemplateKey;
use App\Models\ReservationDayWaitlistEntry;
use App\Models\Service;
use App\Models\User;
use App\Notifications\ReservationDayWaitlistNotification;
use App\Support\Enrollments\JoinWaitlist;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * Adds someone to the "pořadník" for a fully-booked day, scoped to a therapist (or
 * "any" therapist) on a date — never to a service (mirrors {@see JoinWaitlist}).
 *
 * No account is created (joining is non-binding); an existing account is linked by
 * e-mail. Dedup is per (therapist scope, date, person), so the same person can't
 * queue twice for the same therapist-day even across different services.
 */
class JoinReservationDayWaitlist
{
    public function handle(
        ?string $therapistId,
        string $date,
        ?string $name,
        string $email,
        ?string $phone = null,
        ?Service $browsedService = null,
    ): ReservationDayWaitlistEntry {
        $client = User::query()->where('email', $email)->first();

        $existing = ReservationDayWaitlistEntry::query()
            ->whereNull('notified_at')
            ->whereDate('reservation_date', $date)
            ->where(fn ($query) => $therapistId === null
                ? $query->whereNull('therapist_id')
                : $query->where('therapist_id', $therapistId))
            ->where(fn ($query) => $query
                ->where('email', $email)
                ->when($client !== null, fn ($query) => $query->orWhere('client_id', $client->id)))
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $entry = ReservationDayWaitlistEntry::create([
            'client_id' => $client?->id,
            'therapist_id' => $therapistId,
            'service_id' => $browsedService?->getKey(),
            'reservation_date' => $date,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
        ]);

        $notification = new ReservationDayWaitlistNotification(
            EmailTemplateKey::ReservationDayWaitlistJoined,
            [
                'jmeno' => $client !== null
                    ? (string) str((string) $client->name)->before(' ')
                    : (string) str((string) $name)->before(' '),
                'terapeut' => $entry->therapistLabel(),
                'datum' => Carbon::parse($date)->locale('cs')->isoFormat('D. MMMM YYYY'),
            ],
        );

        if ($client !== null) {
            $client->notify($notification);
        } else {
            Notification::route('mail', $email)->notify($notification);
        }

        return $entry;
    }
}
