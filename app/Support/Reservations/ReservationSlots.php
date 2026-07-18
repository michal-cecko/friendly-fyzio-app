<?php

namespace App\Support\Reservations;

use App\Enums\DayOfWeek;
use App\Enums\ReservationStatus;
use App\Enums\ServiceVisibility;
use App\Models\Reservation;
use App\Models\RoomBlocking;
use App\Models\Service;
use App\Models\TherapistWorkBlock;
use App\Support\CalendarAvailability;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Generates the bookable slots a client may pick in the public reservation wizard.
 *
 * Stateless and Octane-safe (mirrors {@see CalendarAvailability}):
 * every call resolves its own data and nothing is cached on the instance. The pure
 * gap-fill maths lives in {@see GapFiller}; this class only feeds it the database
 * picture (work blocks, existing reservations, room blockings) and shapes results
 * into {@see Slot} objects.
 *
 * All slot maths runs in wall-clock minutes since midnight; only the lead-time
 * guard is timezone-aware (Europe/Prague), because reservation times are stored as
 * plain `H:i:s` strings rather than UTC instants.
 */
class ReservationSlots
{
    private const TIMEZONE = 'Europe/Prague';

    /**
     * Dates (Y-m-d) within the inclusive range that offer at least one slot for the
     * service, optionally restricted to one therapist. Data source for step 4.
     *
     * @return array<int, string>
     */
    public function availableDays(Service $service, Carbon $from, Carbon $to, ?string $therapistId = null): array
    {
        return $this->dayAvailability($service, $from, $to, $therapistId)['available'];
    }

    /**
     * Classify every day in the range: 'available' offers at least one bookable slot;
     * 'full' means the therapist works that day but every future slot is already taken
     * (a "pořadník"/waitlist candidate). Days with no work at all appear in neither.
     *
     * Only future dates can be 'full' — a today with no slots left is simply late, not
     * booked out. A day whose room is entirely blocked is a rare false positive.
     *
     * @return array{available: array<int, string>, full: array<int, string>}
     */
    public function dayAvailability(Service $service, Carbon $from, Carbon $to, ?string $therapistId = null): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();
        $baseIds = $this->baseTherapistIds($service, $therapistId);

        if ($baseIds === []) {
            return ['available' => [], 'full' => []];
        }

        $today = Carbon::today();
        $context = $this->preload($baseIds, $from, $to);
        $gapFiller = $this->gapFiller($service);
        $surface = [$service->duration_minutes];

        $available = [];
        $full = [];
        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $workBlocks = $this->workBlocksFromContext($context, $baseIds, $date);

            if ($workBlocks === []) {
                continue;
            }

            $reservations = $this->reservationsFromContext($context, $date);
            $slots = $this->buildSlots(
                $date,
                $baseIds,
                $gapFiller,
                $surface,
                $workBlocks,
                $reservations['byTherapist'],
                $reservations['byRoom'],
                $this->roomBlockingsFromContext($context, $date),
            );

            if ($this->withoutPastSlots($slots, $date) !== []) {
                $available[] = $date->toDateString();
            } elseif ($date->greaterThan($today)) {
                $full[] = $date->toDateString();
            }
        }

        return ['available' => $available, 'full' => $full];
    }

    /**
     * Offerable slots for the service on a single date. Data source for step 5.
     *
     * @return array<int, Slot>
     */
    public function availableTimes(Service $service, Carbon $date, ?string $therapistId = null): array
    {
        $date = $date->copy()->startOfDay();
        $therapistIds = $this->baseTherapistIds($service, $therapistId);

        if ($therapistIds === []) {
            return [];
        }

        $reservations = $this->reservationsForDate($date);
        $slots = $this->buildSlots(
            $date,
            $therapistIds,
            $this->gapFiller($service),
            [$service->duration_minutes],
            $this->workBlocksForDate($therapistIds, $date),
            $reservations['byTherapist'],
            $reservations['byRoom'],
            $this->roomBlockingsForDate($date),
        );

        return $this->withoutPastSlots($slots, $date);
    }

    /**
     * Re-resolve one concrete slot (used at submit time). Returns the live slot —
     * carrying its room — only if that exact start is still offerable for the
     * service/date/therapist, otherwise null.
     */
    public function resolveSlot(Service $service, Carbon $date, string $startTime, string $therapistId): ?Slot
    {
        $startMin = Slot::toMinutes($startTime);

        foreach ($this->availableTimes($service, $date, $therapistId) as $slot) {
            if ($slot->startMin === $startMin && $slot->therapistId === $therapistId) {
                return $slot;
            }
        }

        return null;
    }

    /**
     * Build a configured gap filler for a service's category.
     */
    protected function gapFiller(Service $service): GapFiller
    {
        $services = $service->category
            ? $service->category->services()->get(['duration_minutes', 'visibility'])
            : collect([$service]);

        $all = $services
            ->reject(fn (Service $s): bool => $s->visibility === ServiceVisibility::Hidden)
            ->pluck('duration_minutes')
            ->push($service->duration_minutes)
            ->unique()
            ->values()
            ->all();

        $public = $services
            ->filter(fn (Service $s): bool => $s->visibility === ServiceVisibility::Public)
            ->pluck('duration_minutes')
            ->unique()
            ->values()
            ->all();

        // Fall back to the chainable durations when the category has no public
        // service of its own (e.g. an all-"clients" category).
        if ($public === []) {
            $public = $all;
        }

        return new GapFiller($all, $public, $service->break_minutes, Settings::blockMinutes());
    }

    /**
     * Published therapists that perform the service, optionally pinned to one.
     *
     * @return array<int, string>
     */
    protected function baseTherapistIds(Service $service, ?string $therapistId): array
    {
        // Deliberately not filtered by published_at: publishing only controls the
        // public team page and profile detail, not who can be booked — matching
        // the wizard's therapist picker. Bookability = performs the service and
        // has work blocks in the window.
        return $service->therapists()
            ->when($therapistId, fn ($query) => $query->whereKey($therapistId))
            ->pluck('therapist_profiles.id')
            ->all();
    }

    /**
     * Turn the offers from every therapist + work block into concrete slots.
     *
     * @param  array<int, string>  $therapistIds
     * @param  array<int, int>  $surface
     * @param  array<string, array<int, array{0: int, 1: int, 2: string}>>  $workBlocksByTid
     * @param  array<string, array<int, array{0: int, 1: int}>>  $reservationsByTherapist
     * @param  array<string, array<int, array{0: int, 1: int}>>  $reservationsByRoom
     * @param  array<string, array<int, array{0: int, 1: int}>>  $roomBlockingsByRoom
     * @return array<int, Slot>
     */
    protected function buildSlots(
        Carbon $date,
        array $therapistIds,
        GapFiller $gapFiller,
        array $surface,
        array $workBlocksByTid,
        array $reservationsByTherapist,
        array $reservationsByRoom,
        array $roomBlockingsByRoom,
    ): array {
        $duration = $surface[0];
        $slots = [];

        foreach ($therapistIds as $therapistId) {
            $workBlocks = $workBlocksByTid[$therapistId] ?? [];
            $therapistBusy = $reservationsByTherapist[$therapistId] ?? [];

            foreach ($workBlocks as [$blockStart, $blockEnd, $roomId]) {
                // Room blockings carve the work block into independent sub-blocks.
                $cuts = $roomBlockingsByRoom[$roomId] ?? [];
                // The therapist is busy with their own reservations (in any room); the
                // room is also occupied by anyone else's reservation booked in it.
                $busyAll = $this->mergeIntervals(array_merge($therapistBusy, $reservationsByRoom[$roomId] ?? []));

                foreach ($this->subtractIntervals($blockStart, $blockEnd, $cuts) as [$subStart, $subEnd]) {
                    $busy = $this->clipIntervals($busyAll, $subStart, $subEnd);
                    $offers = $gapFiller->offers($subStart, $subEnd, $busy, $surface)[$duration] ?? [];

                    foreach ($offers as $startMin) {
                        $slots[] = new Slot(
                            date: $date->toDateString(),
                            startMin: $startMin,
                            endMin: $startMin + $duration,
                            therapistId: $therapistId,
                            roomId: $roomId,
                            durationMinutes: $duration,
                        );
                    }
                }
            }
        }

        usort($slots, fn (Slot $a, Slot $b): int => [$a->startMin, $a->therapistId] <=> [$b->startMin, $b->therapistId]);

        return $slots;
    }

    /**
     * Drop slots that start in the past or inside the booking lead window.
     *
     * @param  array<int, Slot>  $slots
     * @return array<int, Slot>
     */
    protected function withoutPastSlots(array $slots, Carbon $date): array
    {
        $cutoff = Carbon::now(self::TIMEZONE)->addHours(Settings::leadTimeHours());

        return array_values(array_filter($slots, function (Slot $slot) use ($cutoff): bool {
            $start = Carbon::parse($slot->date.' '.$slot->start(), self::TIMEZONE);

            return $start->greaterThan($cutoff);
        }));
    }

    /**
     * Remove the cut intervals from [start, end], yielding open sub-intervals.
     *
     * @param  array<int, array{0: int, 1: int}>  $cuts
     * @return array<int, array{0: int, 1: int}>
     */
    protected function subtractIntervals(int $start, int $end, array $cuts): array
    {
        usort($cuts, fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $segments = [];
        $cursor = $start;

        foreach ($cuts as [$cutStart, $cutEnd]) {
            $cutStart = max($cutStart, $start);
            $cutEnd = min($cutEnd, $end);

            if ($cutStart >= $cutEnd) {
                continue;
            }

            if ($cutStart > $cursor) {
                $segments[] = [$cursor, $cutStart];
            }

            $cursor = max($cursor, $cutEnd);
        }

        if ($cursor < $end) {
            $segments[] = [$cursor, $end];
        }

        return $segments;
    }

    /**
     * Clip intervals to [start, end], dropping any that fall outside.
     *
     * @param  array<int, array{0: int, 1: int}>  $intervals
     * @return array<int, array{0: int, 1: int}>
     */
    protected function clipIntervals(array $intervals, int $start, int $end): array
    {
        $clipped = [];

        foreach ($intervals as [$intervalStart, $intervalEnd]) {
            $intervalStart = max($intervalStart, $start);
            $intervalEnd = min($intervalEnd, $end);

            if ($intervalStart < $intervalEnd) {
                $clipped[] = [$intervalStart, $intervalEnd];
            }
        }

        return $clipped;
    }

    /**
     * Sort and merge overlapping or touching intervals so the gap filler receives a
     * clean, non-overlapping busy set (therapist + room reservations may coincide).
     *
     * @param  array<int, array{0: int, 1: int}>  $intervals
     * @return array<int, array{0: int, 1: int}>
     */
    protected function mergeIntervals(array $intervals): array
    {
        usort($intervals, fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $merged = [];
        foreach ($intervals as [$start, $end]) {
            $lastIndex = count($merged) - 1;

            if ($merged !== [] && $start <= $merged[$lastIndex][1]) {
                $merged[$lastIndex][1] = max($merged[$lastIndex][1], $end);
            } else {
                $merged[] = [$start, $end];
            }
        }

        return $merged;
    }

    /**
     * Bucket reservations into busy minute-intervals both by therapist (they are
     * busy in any room) and by room (the room is occupied for everyone).
     *
     * @param  Collection<int, Reservation>  $rows
     * @return array{byTherapist: array<string, array<int, array{0: int, 1: int}>>, byRoom: array<string, array<int, array{0: int, 1: int}>>}
     */
    protected function bucketReservations(Collection $rows): array
    {
        $byTherapist = [];
        $byRoom = [];

        foreach ($rows as $row) {
            $interval = [Slot::toMinutes($row->start_time), Slot::toMinutes($row->end_time)];
            $byTherapist[$row->therapist_id][] = $interval;
            $byRoom[$row->room_id][] = $interval;
        }

        return ['byTherapist' => $byTherapist, 'byRoom' => $byRoom];
    }

    // --- Single-date resolvers (availableTimes / resolveSlot) -----------------

    /**
     * @param  array<int, string>  $therapistIds
     * @return array<string, array<int, array{0: int, 1: int, 2: string}>>
     */
    protected function workBlocksForDate(array $therapistIds, Carbon $date): array
    {
        return TherapistWorkBlock::query()
            ->whereIn('therapist_id', $therapistIds)
            ->whereDate('work_date', $date)
            ->get(['therapist_id', 'start_time', 'end_time', 'room_id'])
            ->groupBy('therapist_id')
            ->map(fn (Collection $rows): array => $rows
                ->map(fn (TherapistWorkBlock $row): array => [
                    Slot::toMinutes($row->start_time), Slot::toMinutes($row->end_time), $row->room_id,
                ])
                ->all())
            ->all();
    }

    /**
     * Non-cancelled reservations on a date, bucketed by therapist and by room.
     * Loads every reservation (not just the service's therapists) so a room occupied
     * by another therapist's booking is respected.
     *
     * @return array{byTherapist: array<string, array<int, array{0: int, 1: int}>>, byRoom: array<string, array<int, array{0: int, 1: int}>>}
     */
    protected function reservationsForDate(Carbon $date): array
    {
        $rows = Reservation::query()
            ->whereDate('reservation_date', $date)
            ->where('status', '!=', ReservationStatus::Cancelled->value)
            ->get(['therapist_id', 'room_id', 'start_time', 'end_time']);

        return $this->bucketReservations($rows);
    }

    /**
     * @return array<string, array<int, array{0: int, 1: int}>>
     */
    protected function roomBlockingsForDate(Carbon $date): array
    {
        $blockings = RoomBlocking::query()
            ->where(fn ($query) => $query
                ->where('is_recurring', true)
                ->orWhere(fn ($inner) => $inner
                    ->where('is_recurring', false)
                    ->where('start_at', '<', $date->copy()->endOfDay())
                    ->where('end_at', '>', $date->copy()->startOfDay())))
            ->get();

        return $this->resolveRoomBlockings($blockings, $date);
    }

    // --- Range preloading (availableDays) -------------------------------------

    /**
     * Load every dataset the day loop needs in a handful of range queries.
     *
     * @param  array<int, string>  $baseIds
     * @return array<string, Collection<int, mixed>>
     */
    protected function preload(array $baseIds, Carbon $from, Carbon $to): array
    {
        return [
            'workBlocks' => TherapistWorkBlock::query()
                ->whereIn('therapist_id', $baseIds)
                ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
                ->get(['therapist_id', 'work_date', 'start_time', 'end_time', 'room_id']),
            'reservations' => Reservation::query()
                ->whereBetween('reservation_date', [$from, $to])
                ->where('status', '!=', ReservationStatus::Cancelled->value)
                ->get(['therapist_id', 'room_id', 'reservation_date', 'start_time', 'end_time']),
            'roomBlockings' => RoomBlocking::query()
                ->where('is_recurring', true)
                ->orWhere(fn ($query) => $query
                    ->where('is_recurring', false)
                    ->where('start_at', '<', $to->copy()->endOfDay())
                    ->where('end_at', '>', $from->copy()->startOfDay()))
                ->get(),
        ];
    }

    /**
     * @param  array<string, Collection<int, mixed>>  $context
     * @param  array<int, string>  $therapistIds
     * @return array<string, array<int, array{0: int, 1: int, 2: string}>>
     */
    protected function workBlocksFromContext(array $context, array $therapistIds, Carbon $date): array
    {
        return $context['workBlocks']
            ->whereIn('therapist_id', $therapistIds)
            ->filter(fn (TherapistWorkBlock $row): bool => $row->work_date->isSameDay($date))
            ->groupBy('therapist_id')
            ->map(fn (Collection $rows): array => $rows
                ->map(fn (TherapistWorkBlock $row): array => [
                    Slot::toMinutes($row->start_time), Slot::toMinutes($row->end_time), $row->room_id,
                ])
                ->all())
            ->all();
    }

    /**
     * @param  array<string, Collection<int, mixed>>  $context
     * @return array{byTherapist: array<string, array<int, array{0: int, 1: int}>>, byRoom: array<string, array<int, array{0: int, 1: int}>>}
     */
    protected function reservationsFromContext(array $context, Carbon $date): array
    {
        $rows = $context['reservations']
            ->filter(fn (Reservation $row): bool => $row->reservation_date->isSameDay($date));

        return $this->bucketReservations($rows);
    }

    /**
     * @param  array<string, Collection<int, mixed>>  $context
     * @return array<string, array<int, array{0: int, 1: int}>>
     */
    protected function roomBlockingsFromContext(array $context, Carbon $date): array
    {
        return $this->resolveRoomBlockings($context['roomBlockings'], $date);
    }

    /**
     * Resolve a set of room blockings into busy minute-intervals per room for one
     * date, expanding recurring rows and clipping one-off rows to the day.
     *
     * @param  Collection<int, RoomBlocking>  $blockings
     * @return array<string, array<int, array{0: int, 1: int}>>
     */
    protected function resolveRoomBlockings(Collection $blockings, Carbon $date): array
    {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->endOfDay();
        $byRoom = [];

        foreach ($blockings as $blocking) {
            if ($blocking->is_recurring) {
                if ($blocking->day_of_week !== DayOfWeek::fromCarbon($date) || ! $blocking->week_type->matchesDate($date)) {
                    continue;
                }

                $interval = [Slot::toMinutes($blocking->start_time), Slot::toMinutes($blocking->end_time)];
            } else {
                if ($blocking->start_at === null || $blocking->end_at === null
                    || $blocking->end_at->lessThanOrEqualTo($dayStart) || $blocking->start_at->greaterThanOrEqualTo($dayEnd)) {
                    continue;
                }

                $start = $blocking->start_at->lessThanOrEqualTo($dayStart) ? 0 : $blocking->start_at->hour * 60 + $blocking->start_at->minute;
                $end = $blocking->end_at->greaterThanOrEqualTo($dayEnd) ? 24 * 60 : $blocking->end_at->hour * 60 + $blocking->end_at->minute;
                $interval = [$start, $end];
            }

            $byRoom[$blocking->room_id][] = $interval;
        }

        return $byRoom;
    }
}
