<?php

namespace App\Support\Reservations;

use App\Enums\EmailTemplateKey;
use App\Models\ReservationDayWaitlistEntry;
use App\Models\TherapistProfile;
use App\Notifications\ReservationDayWaitlistNotification;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * When a slot frees up on a therapist's day, e-mails every pending waiter for that
 * day at once — first to book wins ("open race"). A specific-therapist entry fires
 * only for its own therapist; an "any"-therapist entry fires for whichever therapist
 * freed up. The booking link always points at the concrete freed therapist + day,
 * so the customer lands on a real, bookable slot.
 */
class NotifyReservationDayWaitlist
{
    public function __construct(protected ReservationSlots $slots) {}

    public function __invoke(string $freedTherapistId, string $date): void
    {
        if (! Settings::dayWaitlistEnabled()) {
            return;
        }

        $day = Carbon::parse($date)->startOfDay();

        // Only ever notify for a day that can still be booked.
        if ($day->lessThan(Carbon::today())) {
            return;
        }

        // Re-check the slot is genuinely free now (a reactivation may have re-taken it).
        if (! $this->slots->therapistHasOpening($freedTherapistId, $day)) {
            return;
        }

        $freedTherapist = TherapistProfile::query()->with('user')->find($freedTherapistId);

        if ($freedTherapist === null) {
            return;
        }

        $entries = ReservationDayWaitlistEntry::query()
            ->pending()
            ->whereDate('reservation_date', $day->toDateString())
            ->where(fn ($query) => $query
                ->where('therapist_id', $freedTherapistId)
                ->orWhereNull('therapist_id'))
            ->with(['client', 'service'])
            ->get();

        foreach ($entries as $entry) {
            $this->notify($entry, $freedTherapist, $day);
            $entry->update(['notified_at' => now()]);
        }
    }

    protected function notify(ReservationDayWaitlistEntry $entry, TherapistProfile $freedTherapist, Carbon $day): void
    {
        $name = $entry->displayName();

        $notification = new ReservationDayWaitlistNotification(
            EmailTemplateKey::ReservationDayWaitlistSpotAvailable,
            [
                'jmeno' => (string) str($name)->before(' '),
                'terapeut' => (string) ($freedTherapist->user?->name ?? 'Terapeut'),
                'datum' => $day->locale('cs')->isoFormat('D. MMMM YYYY'),
                'odkaz' => $this->bookingLink($entry, $freedTherapist, $day),
            ],
        );

        if ($entry->client !== null) {
            $entry->client->notify($notification);
        } elseif (filled($entry->email)) {
            Notification::route('mail', $entry->email)->notify($notification);
        }
    }

    /**
     * A wizard deep-link prefilled to the concrete freed therapist + day, carrying
     * the browsed service too when the entry recorded one.
     */
    protected function bookingLink(ReservationDayWaitlistEntry $entry, TherapistProfile $freedTherapist, Carbon $day): string
    {
        $params = [
            'terapeut' => $freedTherapist->slug,
            'datum' => $day->toDateString(),
        ];

        if ($entry->service !== null) {
            $params['sluzba'] = $entry->service->slug;
        }

        return url('/rezervace').'?'.http_build_query($params);
    }
}
