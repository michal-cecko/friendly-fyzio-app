<?php

namespace App\Support\Reservations;

use App\Enums\ReservationStatus;
use App\Models\Lesson;
use App\Models\Reservation;
use App\Models\RoomBlocking;
use App\Models\Service;
use App\Models\StaffProfile;
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
 * picture (work blocks, existing reservations, lessons, room blockings) and shapes
 * results into {@see Slot} objects.
 *
 * Lessons — course lessons and one-off events alike — count as busy time exactly
 * like a reservation: they occupy their room for everyone, and when the lecturer
 * is a bookable therapist they occupy that therapist in every room. They are
 * deliberately *not* treated as cuts the way room blockings are; see
 * {@see busyForDate()}.
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
        $gapFillers = $this->gapFillers($service, $baseIds, $context['breaks']);
        $surface = [$service->duration_minutes];

        $available = [];
        $full = [];
        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $workBlocks = $this->workBlocksFromContext($context, $baseIds, $date);

            if ($workBlocks === []) {
                continue;
            }

            $busy = $this->busyFromContext($context, $date);
            $slots = $this->buildSlots(
                $date,
                $baseIds,
                $gapFillers,
                $surface,
                $workBlocks,
                $busy['byTherapist'],
                $busy['byRoom'],
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
     * Whether a therapist has any bookable opening on a date, independent of
     * service. Used by the day-waitlist notifier to confirm a freed slot is still
     * free (e.g. a reactivation may have re-taken it) before e-mailing waiters.
     *
     * Service-agnostic on purpose: the waitlist is a therapist's whole day, so any
     * future, past-lead-time gap of at least one block ({@see Settings::blockMinutes()})
     * in their work blocks — after their own reservations and lessons, whatever else
     * occupies the room, and room blockings are removed — counts as an opening.
     */
    public function therapistHasOpening(string $therapistId, Carbon $date): bool
    {
        $date = $date->copy()->startOfDay();
        $workBlocks = $this->workBlocksForDate([$therapistId], $date)[$therapistId] ?? [];

        if ($workBlocks === []) {
            return false;
        }

        $busyBuckets = $this->busyForDate($date, [$therapistId], new BreakResolver);
        $therapistBusy = $busyBuckets['byTherapist'][$therapistId] ?? [];
        $roomBlockings = $this->roomBlockingsForDate($date);

        $minDuration = Settings::blockMinutes();
        $cutoffMin = $this->leadCutoffMinutes($date);

        foreach ($workBlocks as [$blockStart, $blockEnd, $roomId]) {
            $busyAll = $this->mergeIntervals(array_merge($therapistBusy, $busyBuckets['byRoom'][$roomId] ?? []));

            foreach ($this->subtractIntervals($blockStart, $blockEnd, $roomBlockings[$roomId] ?? []) as [$subStart, $subEnd]) {
                $busy = $this->clipIntervals($busyAll, $subStart, $subEnd);

                foreach ($this->subtractIntervals($subStart, $subEnd, $busy) as [$freeStart, $freeEnd]) {
                    if ($freeEnd - max($freeStart, $cutoffMin) >= $minDuration) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * The earliest bookable minute-of-day on a date given the lead-time guard:
     * 0 for a future day, the cutoff's minute-of-day for today, or a value past
     * midnight (so nothing qualifies) once the whole day is inside the lead window.
     */
    protected function leadCutoffMinutes(Carbon $date): int
    {
        $cutoff = Carbon::now(self::TIMEZONE)->addHours(Settings::leadTimeHours());

        if ($date->greaterThan($cutoff->copy()->startOfDay())) {
            return 0;
        }

        if ($date->lessThan($cutoff->copy()->startOfDay())) {
            return 24 * 60 + 1;
        }

        return $cutoff->hour * 60 + $cutoff->minute;
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

        $breaks = new BreakResolver;
        $busy = $this->busyForDate($date, $therapistIds, $breaks);
        $slots = $this->buildSlots(
            $date,
            $therapistIds,
            $this->gapFillers($service, $therapistIds, $breaks),
            [$service->duration_minutes],
            $this->workBlocksForDate($therapistIds, $date),
            $busy['byTherapist'],
            $busy['byRoom'],
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
     * One configured gap filler per therapist, because the break folded into a
     * booking's footprint is theirs and nobody else's. The category's services
     * are read once and costed separately for each of them.
     *
     * Only services this therapist actually performs count: the anchor lattice
     * decides what is offered, so a length only a colleague can deliver must not
     * shift the anchors in this therapist's day.
     *
     * @param  array<int, string>  $therapistIds
     * @return array<string, GapFiller>
     */
    protected function gapFillers(Service $service, array $therapistIds, BreakResolver $breaks): array
    {
        $services = $service->category
            ? $service->category->services()->bookable()->with('therapists:id')->get(['id', 'duration_minutes'])
            : collect([$service]);

        $fillers = [];

        foreach ($therapistIds as $therapistId) {
            $cost = fn (Service $sibling): int => $sibling->duration_minutes
                + $breaks->minutesFor($therapistId, $sibling->getKey());

            $anchors = [];

            foreach ($services as $sibling) {
                if (! $sibling->therapists->contains(fn (StaffProfile $profile): bool => $profile->getKey() === $therapistId)) {
                    continue;
                }

                // Two services of the same length may cost different amounts of
                // this therapist's time. For chaining, the cheaper one wins — if
                // any booking of that length can tile the gap, the gap is fillable.
                $duration = $sibling->duration_minutes;
                $anchors[$duration] = min($anchors[$duration] ?? PHP_INT_MAX, $cost($sibling));
            }

            // The service actually being placed costs exactly what it costs, even
            // where a same-length sibling is cheaper. It may also be one nobody can
            // book online (a hidden service placed by staff or by a reschedule), in
            // which case it still chains but must not generate anchors of its own.
            $all = $anchors;
            $all[$service->duration_minutes] = $cost($service);

            $fillers[$therapistId] = new GapFiller($all, $anchors === [] ? $all : $anchors);
        }

        return $fillers;
    }

    /**
     * Published therapists that perform the service, optionally pinned to one.
     *
     * @return array<int, string>
     */
    protected function baseTherapistIds(Service $service, ?string $therapistId): array
    {
        // Deliberately not filtered by published_at: publishing only controls the
        // public team page and profile detail, not who can be booked. The
        // `bookable` scope pins this to actual therapists (Therapist capability +
        // active account), so "Nezáleží" only ever spreads across therapists —
        // never a lecturer or the assistant who happens to link the service.
        return $service->therapists()
            ->bookable()
            ->when($therapistId, fn ($query) => $query->whereKey($therapistId))
            ->pluck('staff_profiles.id')
            ->all();
    }

    /**
     * Turn the offers from every therapist + work block into concrete slots.
     *
     * @param  array<int, string>  $therapistIds
     * @param  array<string, GapFiller>  $gapFillers
     * @param  array<int, int>  $surface
     * @param  array<string, array<int, array{0: int, 1: int, 2: string}>>  $workBlocksByTid
     * @param  array<string, array<int, array{0: int, 1: int, 2: int}>>  $busyByTherapist
     * @param  array<string, array<int, array{0: int, 1: int, 2: int}>>  $busyByRoom
     * @param  array<string, array<int, array{0: int, 1: int}>>  $roomBlockingsByRoom
     * @return array<int, Slot>
     */
    protected function buildSlots(
        Carbon $date,
        array $therapistIds,
        array $gapFillers,
        array $surface,
        array $workBlocksByTid,
        array $busyByTherapist,
        array $busyByRoom,
        array $roomBlockingsByRoom,
    ): array {
        $duration = $surface[0];
        $slots = [];

        foreach ($therapistIds as $therapistId) {
            $workBlocks = $workBlocksByTid[$therapistId] ?? [];
            $therapistBusy = $busyByTherapist[$therapistId] ?? [];
            $gapFiller = $gapFillers[$therapistId] ?? null;

            if ($gapFiller === null) {
                continue;
            }

            foreach ($workBlocks as [$blockStart, $blockEnd, $roomId]) {
                // Room blockings carve the work block into independent sub-blocks.
                $cuts = $roomBlockingsByRoom[$roomId] ?? [];
                // The therapist is busy with their own reservations and lessons (in any
                // room); the room is also occupied by anyone else's reservation or
                // lesson held in it.
                $busyAll = $this->mergeIntervals(array_merge($therapistBusy, $busyByRoom[$roomId] ?? []));

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
     * Only the bounds are read, so this takes busy intervals (which carry a
     * trailing break) just as happily as plain room blockings — the break is
     * deliberately ignored, since this answers "what time is unoccupied", not
     * "what time is bookable".
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
     * Clip busy intervals to [start, end], dropping any that fall outside. The
     * break rides along untouched — it is owed after the booking wherever the
     * window happens to be cut.
     *
     * @param  array<int, array{0: int, 1: int, 2: int}>  $intervals
     * @return array<int, array{0: int, 1: int, 2: int}>
     */
    protected function clipIntervals(array $intervals, int $start, int $end): array
    {
        $clipped = [];

        foreach ($intervals as [$intervalStart, $intervalEnd, $breakAfter]) {
            $intervalStart = max($intervalStart, $start);
            $intervalEnd = min($intervalEnd, $end);

            if ($intervalStart < $intervalEnd) {
                $clipped[] = [$intervalStart, $intervalEnd, $breakAfter];
            }
        }

        return $clipped;
    }

    /**
     * Sort and merge overlapping or touching busy intervals so the gap filler
     * receives a clean, non-overlapping set (therapist + room reservations may
     * coincide). A merged block is free again at the latest moment any of its
     * parts is free again — end plus break — which the surviving break is sized
     * to reproduce. A long break behind an early-finishing booking therefore
     * still counts.
     *
     * @param  array<int, array{0: int, 1: int, 2: int}>  $intervals
     * @return array<int, array{0: int, 1: int, 2: int}>
     */
    protected function mergeIntervals(array $intervals): array
    {
        usort($intervals, fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $merged = [];
        foreach ($intervals as [$start, $end, $breakAfter]) {
            $lastIndex = count($merged) - 1;

            if ($merged !== [] && $start <= $merged[$lastIndex][1]) {
                $freeAgain = max($merged[$lastIndex][1] + $merged[$lastIndex][2], $end + $breakAfter);
                $merged[$lastIndex][1] = max($merged[$lastIndex][1], $end);
                $merged[$lastIndex][2] = $freeAgain - $merged[$lastIndex][1];
            } else {
                $merged[] = [$start, $end, $breakAfter];
            }
        }

        return $merged;
    }

    /**
     * Bucket reservations into busy minute-intervals both by therapist (they are
     * busy in any room) and by room (the room is occupied for everyone).
     *
     * Each interval carries the break frozen onto the reservation when it was
     * booked, so no lookup is needed here and a therapist who has since changed
     * their default does not retroactively move bookings that already exist.
     *
     * @param  Collection<int, Reservation>  $rows
     * @return array{byTherapist: array<string, array<int, array{0: int, 1: int, 2: int}>>, byRoom: array<string, array<int, array{0: int, 1: int, 2: int}>>}
     */
    protected function bucketReservations(Collection $rows): array
    {
        $byTherapist = [];
        $byRoom = [];

        foreach ($rows as $row) {
            $interval = [Slot::toMinutes($row->start_time), Slot::toMinutes($row->end_time), (int) $row->break_minutes];
            $byTherapist[$row->therapist_id][] = $interval;
            $byRoom[$row->room_id][] = $interval;
        }

        return ['byTherapist' => $byTherapist, 'byRoom' => $byRoom];
    }

    /**
     * The same bucketing for lessons. A lesson always occupies its room; it only
     * occupies a therapist when its lecturer maps to one of the profiles we are
     * offering — a lecturer-only account has no bookable profile and therefore
     * blocks nothing but the room.
     *
     * A lesson has no service to hang an override on, so it leaves behind its
     * lecturer's own default break — resolved from the lecturer directly rather
     * than through $profileIdByUser, because a therapist teaching a class needs
     * their rest whether or not we happen to be offering their slots. An
     * external lecturer with no staff profile leaves none.
     *
     * @param  Collection<int, Lesson>  $rows
     * @param  array<string, string>  $profileIdByUser  users.id => staff_profiles.id
     * @return array{byTherapist: array<string, array<int, array{0: int, 1: int, 2: int}>>, byRoom: array<string, array<int, array{0: int, 1: int, 2: int}>>}
     */
    protected function bucketLessons(Collection $rows, array $profileIdByUser, BreakResolver $breaks): array
    {
        $byTherapist = [];
        $byRoom = [];

        foreach ($rows as $row) {
            $profileId = $profileIdByUser[$row->instructor_id] ?? null;
            $interval = [
                Slot::toMinutes($row->start_time),
                Slot::toMinutes($row->end_time),
                $breaks->defaultMinutesForUser($row->instructor_id),
            ];

            if ($row->room_id !== null) {
                $byRoom[$row->room_id][] = $interval;
            }

            if ($profileId !== null) {
                $byTherapist[$profileId][] = $interval;
            }
        }

        return ['byTherapist' => $byTherapist, 'byRoom' => $byRoom];
    }

    /**
     * Concatenate two {byTherapist, byRoom} bucket sets per key. The gap filler
     * receives the union; {@see mergeIntervals()} normalises any overlap.
     *
     * @param  array{byTherapist: array<string, array<int, array{0: int, 1: int}>>, byRoom: array<string, array<int, array{0: int, 1: int}>>}  $first
     * @param  array{byTherapist: array<string, array<int, array{0: int, 1: int}>>, byRoom: array<string, array<int, array{0: int, 1: int}>>}  $second
     * @return array{byTherapist: array<string, array<int, array{0: int, 1: int}>>, byRoom: array<string, array<int, array{0: int, 1: int}>>}
     */
    protected function mergeBuckets(array $first, array $second): array
    {
        foreach (['byTherapist', 'byRoom'] as $dimension) {
            foreach ($second[$dimension] as $key => $intervals) {
                $first[$dimension][$key] = [...($first[$dimension][$key] ?? []), ...$intervals];
            }
        }

        return $first;
    }

    /**
     * Everything that makes a therapist or a room unavailable on a date without
     * removing the working hour itself: reservations plus lessons.
     *
     * Lessons are busy time rather than cuts on purpose. A cut (how room
     * blockings work) makes the block behave as if it never existed for that
     * window, so a booking could end at the very minute a course starts and the
     * turnaround rules restart afterwards. Busy time gives a lesson exactly the
     * semantics a reservation has — the trailing break, plus the gap filler's
     * strict gluing, so the gap before a lesson is only offered when it can be
     * tiled exactly.
     *
     * @param  array<int, string>  $therapistIds
     * @return array{byTherapist: array<string, array<int, array{0: int, 1: int}>>, byRoom: array<string, array<int, array{0: int, 1: int}>>}
     */
    protected function busyForDate(Carbon $date, array $therapistIds, BreakResolver $breaks): array
    {
        $lessons = Lesson::query()
            ->whereDate('lesson_date', $date)
            ->get(['instructor_id', 'room_id', 'start_time', 'end_time']);

        return $this->mergeBuckets(
            $this->reservationsForDate($date),
            $this->bucketLessons($lessons, $this->profileIdByUser($therapistIds), $breaks),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{byTherapist: array<string, array<int, array{0: int, 1: int}>>, byRoom: array<string, array<int, array{0: int, 1: int}>>}
     */
    protected function busyFromContext(array $context, Carbon $date): array
    {
        $lessons = $context['lessons']
            ->filter(fn (Lesson $row): bool => $row->lesson_date->isSameDay($date));

        return $this->mergeBuckets(
            $this->reservationsFromContext($context, $date),
            $this->bucketLessons($lessons, $context['profileIdByUser'], $context['breaks']),
        );
    }

    /**
     * Map users.id => staff_profiles.id for the profiles being offered. Scoped to
     * them because only their busy intervals are ever read, and because it keeps
     * a non-bookable profile from leaking in through a lesson.
     *
     * @param  array<int, string>  $therapistIds
     * @return array<string, string>
     */
    protected function profileIdByUser(array $therapistIds): array
    {
        return StaffProfile::query()
            ->whereKey($therapistIds)
            ->pluck('id', 'user_id')
            ->all();
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
            ->get(['therapist_id', 'room_id', 'start_time', 'end_time', 'break_minutes']);

        return $this->bucketReservations($rows);
    }

    /**
     * @return array<string, array<int, array{0: int, 1: int}>>
     */
    protected function roomBlockingsForDate(Carbon $date): array
    {
        return $this->resolveRoomBlockings(RoomBlockingIntervals::inRange($date, $date)->get(), $date);
    }

    // --- Range preloading (availableDays) -------------------------------------

    /**
     * Load every dataset the day loop needs in a handful of range queries — the
     * whole window at once, never per day.
     *
     * @param  array<int, string>  $baseIds
     * @return array{workBlocks: Collection<int, TherapistWorkBlock>, reservations: Collection<int, Reservation>, lessons: Collection<int, Lesson>, roomBlockings: Collection<int, RoomBlocking>, profileIdByUser: array<string, string>, breaks: BreakResolver}
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
                ->get(['therapist_id', 'room_id', 'reservation_date', 'start_time', 'end_time', 'break_minutes']),
            // Half-open bounds: lesson_date is stored as a full datetime, so an
            // inclusive `Y-m-d` upper bound would drop the last day under
            // sqlite's text comparison. The day loop re-filters anyway, so
            // over-reaching by a few hours costs nothing.
            'lessons' => Lesson::query()
                ->where('lesson_date', '>=', $from->toDateString())
                ->where('lesson_date', '<', $to->copy()->addDay()->toDateString())
                ->get(['instructor_id', 'room_id', 'lesson_date', 'start_time', 'end_time']),
            'roomBlockings' => RoomBlockingIntervals::inRange($from, $to)->get(),
            'profileIdByUser' => $this->profileIdByUser($baseIds),
            // One resolver for the whole window, so the two break tables are read
            // once rather than once per day of the loop.
            'breaks' => new BreakResolver,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
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
     * @param  array<string, mixed>  $context
     * @return array{byTherapist: array<string, array<int, array{0: int, 1: int}>>, byRoom: array<string, array<int, array{0: int, 1: int}>>}
     */
    protected function reservationsFromContext(array $context, Carbon $date): array
    {
        $rows = $context['reservations']
            ->filter(fn (Reservation $row): bool => $row->reservation_date->isSameDay($date));

        return $this->bucketReservations($rows);
    }

    /**
     * @param  array<string, mixed>  $context
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
        return RoomBlockingIntervals::byRoom($blockings, $date);
    }
}
