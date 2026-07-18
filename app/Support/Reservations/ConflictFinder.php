<?php

namespace App\Support\Reservations;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Detects overlapping reservations that share a room or a therapist within a
 * date window — the "detekcia konfliktov" the admin panel must surface (same
 * room double-booked, therapist overlap). Touching intervals (one ends exactly
 * when the next starts) are NOT a conflict.
 */
final class ConflictFinder
{
    /**
     * @return list<array{type: 'room'|'therapist', date: string, a: Reservation, b: Reservation}>
     */
    public static function find(CarbonInterface $from, CarbonInterface $to): array
    {
        $reservations = Reservation::query()
            ->whereBetween('reservation_date', [$from->toDateString(), $to->toDateString()])
            ->whereNot('status', ReservationStatus::Cancelled)
            ->with(['client', 'service', 'therapist.user', 'room'])
            ->orderBy('reservation_date')
            ->orderBy('start_time')
            ->get();

        return [
            ...self::sweep($reservations, 'room', 'room_id'),
            ...self::sweep($reservations, 'therapist', 'therapist_id'),
        ];
    }

    /**
     * @param  Collection<int, Reservation>  $reservations
     * @return list<array{type: 'room'|'therapist', date: string, a: Reservation, b: Reservation}>
     */
    private static function sweep($reservations, string $type, string $key): array
    {
        $conflicts = [];

        // Group by date + entity id, skipping rows with no room/therapist assigned.
        $groups = $reservations
            ->filter(fn (Reservation $r): bool => $r->{$key} !== null)
            ->groupBy(fn (Reservation $r): string => $r->reservation_date->toDateString().'|'.$r->{$key});

        foreach ($groups as $group) {
            // Already ordered by start_time from the query; sweep with a running
            // "latest end so far" so a long block overlapping several later ones
            // is caught against each of them.
            $running = null;
            $runningEnd = -1;

            foreach ($group as $reservation) {
                $start = Slot::toMinutes($reservation->start_time);
                $end = Slot::toMinutes($reservation->end_time);

                if ($running !== null && $start < $runningEnd) {
                    $conflicts[] = [
                        'type' => $type,
                        'date' => $reservation->reservation_date->toDateString(),
                        'a' => $running,
                        'b' => $reservation,
                    ];
                }

                if ($end > $runningEnd) {
                    $running = $reservation;
                    $runningEnd = $end;
                }
            }
        }

        return $conflicts;
    }

    /**
     * Convenience for the widget: conflicts from today through the next $days days.
     *
     * @return list<array{type: 'room'|'therapist', date: string, a: Reservation, b: Reservation}>
     */
    public static function upcoming(int $days = 7): array
    {
        return self::find(Carbon::today(), Carbon::today()->addDays($days));
    }

    /**
     * Other non-cancelled reservations that clash with this one — same day and
     * overlapping time, sharing its room or its therapist. Used to warn on the
     * reservation detail page.
     *
     * @return list<array{type: 'room'|'therapist', other: Reservation}>
     */
    public static function forReservation(Reservation $reservation): array
    {
        $start = Slot::toMinutes($reservation->start_time);
        $end = Slot::toMinutes($reservation->end_time);

        $others = Reservation::query()
            ->whereDate('reservation_date', $reservation->reservation_date->toDateString())
            ->whereNot('status', ReservationStatus::Cancelled)
            ->whereKeyNot($reservation->getKey())
            ->where(fn ($query) => $query
                ->where('room_id', $reservation->room_id)
                ->orWhere('therapist_id', $reservation->therapist_id))
            ->with(['client', 'service', 'therapist.user', 'room'])
            ->orderBy('start_time')
            ->get();

        $conflicts = [];

        foreach ($others as $other) {
            $overlaps = Slot::toMinutes($other->start_time) < $end
                && Slot::toMinutes($other->end_time) > $start;

            if (! $overlaps) {
                continue;
            }

            if ($other->room_id === $reservation->room_id) {
                $conflicts[] = ['type' => 'room', 'other' => $other];
            }

            if ($other->therapist_id === $reservation->therapist_id) {
                $conflicts[] = ['type' => 'therapist', 'other' => $other];
            }
        }

        return $conflicts;
    }
}
