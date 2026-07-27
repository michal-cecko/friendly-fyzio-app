<?php

namespace App\Observers;

use App\Enums\ReservationStatus;
use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Models\Reservation;
use App\Models\ReservationDayWaitlistEntry;
use App\Support\Mentions\StaffMentions;
use App\Support\Reservations\BreakResolver;
use App\Support\Reservations\NotifyReservationDayWaitlist;

/**
 * Reacts to reservation writes:
 *  - freezes the therapist's break onto the reservation, so the schedule keeps
 *    showing the gap the visit was booked with even after that therapist
 *    changes their default ({@see BreakResolver});
 *  - notifies staff members newly @-mentioned in the internal note (an observer,
 *    rather than form hooks, covers every write path — resource pages, table edit
 *    modal, calendar widget, and the public wizard);
 *  - when a slot frees on a therapist's day (cancellation, reschedule away, or
 *    soft-delete), e-mails that day's "pořadník" ({@see NotifyReservationDayWaitlist}).
 */
class ReservationObserver
{
    /**
     * A caller that sets the break itself is taken at its word — an import
     * replaying history knows better than the therapist's current profile.
     */
    public function creating(Reservation $reservation): void
    {
        if ($reservation->isDirty('break_minutes')) {
            return;
        }

        $reservation->break_minutes = BreakResolver::freshMinutesFor(
            $reservation->therapist_id,
            $reservation->service_id,
        );
    }

    /**
     * Moving a visit to another therapist — or changing what is being done —
     * changes whose break follows it, and how long it is.
     */
    public function updating(Reservation $reservation): void
    {
        if ($reservation->isDirty('break_minutes') || ! $reservation->isDirty(['therapist_id', 'service_id'])) {
            return;
        }

        $reservation->break_minutes = BreakResolver::freshMinutesFor(
            $reservation->therapist_id,
            $reservation->service_id,
        );
    }

    public function created(Reservation $reservation): void
    {
        $this->notifyMentions($reservation, old: null);

        // The booker got a slot that day → drop their own pending day-waitlist entry.
        if ($reservation->client_id !== null) {
            ReservationDayWaitlistEntry::query()
                ->whereNull('notified_at')
                ->where('client_id', $reservation->client_id)
                ->whereDate('reservation_date', $reservation->reservation_date->toDateString())
                ->delete();
        }
    }

    public function updated(Reservation $reservation): void
    {
        if ($reservation->wasChanged('notes')) {
            $this->notifyMentions($reservation, old: $reservation->getOriginal('notes'));
        }

        // Cancellation frees the current slot on the reservation's therapist/day.
        if ($reservation->wasChanged('status') && $reservation->status === ReservationStatus::Cancelled) {
            $this->notifyDayWaitlist($reservation->therapist_id, $reservation->reservation_date);
        }

        // A reschedule (date and/or therapist changed) frees the ORIGINAL day, whose
        // status is untouched — notify the day the reservation moved away from.
        if ($reservation->wasChanged('reservation_date') || $reservation->wasChanged('therapist_id')) {
            $this->notifyDayWaitlist(
                $reservation->getOriginal('therapist_id'),
                $reservation->getOriginal('reservation_date'),
            );
        }
    }

    public function deleted(Reservation $reservation): void
    {
        $this->notifyDayWaitlist($reservation->therapist_id, $reservation->reservation_date);
    }

    /**
     * Fire the day-waitlist notifier for a freed (therapist, date). The notifier
     * itself no-ops on past dates, a re-taken slot, or when the feature is off.
     */
    private function notifyDayWaitlist(?string $therapistId, mixed $date): void
    {
        if ($therapistId === null || $date === null) {
            return;
        }

        $dateString = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : (string) $date;

        app(NotifyReservationDayWaitlist::class)($therapistId, $dateString);
    }

    private function notifyMentions(Reservation $reservation, ?string $old): void
    {
        if (! StaffMentions::containsMentions($reservation->notes)) {
            return;
        }

        $actor = auth()->user();

        StaffMentions::notifyNewMentions(
            old: $old,
            new: $reservation->notes,
            author: $actor,
            title: ($actor?->name ?? 'Někdo').' vás zmínil/a v poznámce k rezervaci klienta '
                .$reservation->client?->name
                .' ('.$reservation->reservation_date?->format('d.m.Y').')',
            url: ReservationResource::getUrl('view', ['record' => $reservation]),
        );
    }
}
