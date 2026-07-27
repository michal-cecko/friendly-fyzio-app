<?php

namespace App\Support\Reservations;

use App\Enums\ConflictSeverity;
use App\Enums\ReservationStatus;
use App\Models\Lesson;
use App\Models\Reservation;
use App\Models\RoomBlocking;
use App\Models\StaffProfile;
use App\Models\TherapistWorkBlock;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Detects everything that competes for the same room or the same person within a
 * date window — the "detekcia konfliktov" the admin panel must surface.
 *
 * Four kinds of record occupy time and are swept against each other: existing
 * reservations, course lessons and one-off events, a therapist's working hours,
 * and room blockings. Which pairings actually mean something is spelled out in
 * {@see self::RULES}; anything not listed there is silently normal. Notably
 * absent, on purpose:
 *
 * - reservation × work block — a booking inside its own working hours is the
 *   entire point of working hours;
 * - work block × work block — already prevented when the block is created
 *   ({@see TherapistWorkBlock::overlapsQuery()});
 * - blocking × lesson and blocking × blocking — a blocked room is administrative
 *   bookkeeping, not a clash with a scheduled lesson.
 *
 * A work block overlapping a blocking IS listed, but only as
 * {@see ConflictSeverity::Soft}: {@see ReservationSlots} already subtracts
 * blockings before offering slots, so it is an overview line, not a fault. Every
 * other listed pairing is something a client could still be booked into.
 *
 * Visits reconstructed from a historical import are always excluded: their times
 * are placeholders assigned at import (typically all the same hour), so they
 * would otherwise register as a wall of same-room "conflicts" that never
 * happened. This mirrors the calendar, which also hides imported visits.
 *
 * Conflict state is likewise "rolling": only present/future bookings are flagged
 * on the reservation detail page. Past reservations — historical, possibly seeded
 * with imperfect data — are kept on record but never surface a conflict warning.
 *
 * Overlaps are reported pairwise: three things overlapping each other yield three
 * conflicts, not one chain.
 */
final class ConflictFinder
{
    /**
     * Which overlapping pairs are worth reporting, per dimension, as
     * `[severity, Czech headline]`. Keys are the two record kinds sorted
     * alphabetically and joined with a pipe.
     *
     * @var array<string, array<string, array{0: ConflictSeverity, 1: string}>>
     */
    private const RULES = [
        'room' => [
            'reservation|reservation' => [ConflictSeverity::Hard, 'Dvojí rezervace místnosti'],
            'lesson|reservation' => [ConflictSeverity::Hard, 'Rezervace a lekce ve stejné místnosti'],
            'blocking|reservation' => [ConflictSeverity::Hard, 'Rezervace v blokované místnosti'],
            'lesson|workBlock' => [ConflictSeverity::Hard, 'Lekce zabírá místnost v pracovní době'],
            'lesson|lesson' => [ConflictSeverity::Hard, 'Dvě lekce ve stejné místnosti'],
            'blocking|workBlock' => [ConflictSeverity::Soft, 'Blokace uvnitř pracovní doby'],
        ],
        'therapist' => [
            'reservation|reservation' => [ConflictSeverity::Hard, 'Dvojí rezervace terapeuta'],
            'lesson|reservation' => [ConflictSeverity::Hard, 'Rezervace a lekce ve stejný čas'],
            'lesson|workBlock' => [ConflictSeverity::Hard, 'Lektor učí ve své pracovní době'],
            'lesson|lesson' => [ConflictSeverity::Hard, 'Lektor má dvě lekce naráz'],
        ],
    ];

    /**
     * @return list<Conflict>
     */
    public static function find(CarbonInterface $from, CarbonInterface $to): array
    {
        $data = self::load($from, $to);
        $end = Carbon::parse($to->toDateString());

        $conflicts = [];
        for ($date = Carbon::parse($from->toDateString()); $date->lte($end); $date->addDay()) {
            $occurrences = self::occurrences($data, $date);

            if (count($occurrences) < 2) {
                continue;
            }

            $day = $date->toDateString();

            $conflicts = [
                ...$conflicts,
                ...self::pairs($occurrences, 'room', $day),
                ...self::pairs($occurrences, 'therapist', $day),
            ];
        }

        return $conflicts;
    }

    /**
     * Convenience for the widget: conflicts from today through the next $days days.
     *
     * @return list<Conflict>
     */
    public static function upcoming(int $days = 7): array
    {
        return self::find(Carbon::today(), Carbon::today()->addDays($days));
    }

    /**
     * Collapse conflicts that are the same pattern repeating on different dates —
     * one recurring rental against one therapist's working hours is a single thing
     * to look at, whatever it lands on. The surviving conflict keeps the earliest
     * date and counts how many times it recurs.
     *
     * @param  list<Conflict>  $conflicts
     * @return list<Conflict>
     */
    public static function collapseRecurring(array $conflicts): array
    {
        $grouped = [];

        foreach ($conflicts as $conflict) {
            $key = $conflict->recurrenceKey();

            $grouped[$key] = isset($grouped[$key])
                ? $grouped[$key]->withOccurrences($grouped[$key]->occurrences + 1)
                : $conflict;
        }

        return array_values($grouped);
    }

    /**
     * Everything that clashes with this reservation on its own date, with the
     * reservation itself always as side `a`. Used to warn on the detail page.
     *
     * @return list<Conflict>
     */
    public static function forReservation(Reservation $reservation): array
    {
        // Conflict-checking only makes sense for present/future bookings the
        // admin can still act on. Imported visits carry placeholder times, and
        // anything dated before today is historical (possibly seeded) data we
        // keep on record but never flag — so skip both.
        if ($reservation->imported_at !== null || $reservation->reservation_date->isBefore(today())) {
            return [];
        }

        $id = (string) $reservation->getKey();
        $date = $reservation->reservation_date;

        $conflicts = array_filter(
            self::find($date, $date),
            fn (Conflict $conflict): bool => $conflict->involves('reservation', $id),
        );

        return array_values(array_map(
            fn (Conflict $conflict): Conflict => $conflict->a->matches('reservation', $id) ? $conflict : $conflict->flipped(),
            $conflicts,
        ));
    }

    /**
     * Every time-occupying record in the window, in one query per source, grouped
     * by date where that makes sense.
     *
     * @return array{reservations: Collection<string, Collection<int, Reservation>>, lessons: Collection<string, Collection<int, Lesson>>, workBlocks: Collection<string, Collection<int, TherapistWorkBlock>>, blockings: Collection<int, RoomBlocking>, profileIdByUser: array<string, string>}
     */
    private static function load(CarbonInterface $from, CarbonInterface $to): array
    {
        $reservations = Reservation::query()
            ->whereNull('imported_at')
            ->tap(self::withinDates('reservation_date', $from, $to))
            ->whereNot('status', ReservationStatus::Cancelled)
            ->with(['client', 'service', 'therapist.user', 'room'])
            ->get();

        $lessons = Lesson::query()
            ->tap(self::withinDates('lesson_date', $from, $to))
            ->with(['series.course', 'instructor', 'room'])
            ->get();

        $workBlocks = TherapistWorkBlock::query()
            ->tap(self::withinDates('work_date', $from, $to))
            ->with(['therapist.user', 'room'])
            ->get();

        // lessons.instructor_id points at users.id while everything else keys on
        // staff_profiles.id — a lecturer who is not a therapist simply has no
        // entry here and can therefore only ever clash over a room.
        $instructorIds = $lessons->pluck('instructor_id')->filter()->unique()->values();

        return [
            'reservations' => $reservations->groupBy(fn (Reservation $row): string => $row->reservation_date->toDateString()),
            'lessons' => $lessons->groupBy(fn (Lesson $row): string => $row->lesson_date->toDateString()),
            'workBlocks' => $workBlocks->groupBy(fn (TherapistWorkBlock $row): string => $row->work_date->toDateString()),
            'blockings' => RoomBlockingIntervals::inRange($from, $to)->with('room')->get(),
            'profileIdByUser' => $instructorIds->isEmpty()
                ? []
                : StaffProfile::query()->whereIn('user_id', $instructorIds)->pluck('id', 'user_id')->all(),
        ];
    }

    /**
     * A half-open date filter, deliberately not `whereBetween` or `whereDate`.
     *
     * These columns do not agree on a storage format — a reservation date lands
     * as `Y-m-d H:i:s` while a work date is normalised to `Y-m-d` — and sqlite
     * compares them as text, so an inclusive upper bound of `Y-m-d` silently
     * drops the whole last day of one and `Y-m-d H:i:s` drops the other.
     * `>= from AND < to+1day` is correct for both formats and still uses the
     * index, which `whereDate()` would not.
     *
     * @return \Closure(Builder): void
     */
    private static function withinDates(string $column, CarbonInterface $from, CarbonInterface $to): \Closure
    {
        return function ($query) use ($column, $from, $to): void {
            $query->where($column, '>=', $from->toDateString())
                ->where($column, '<', Carbon::parse($to->toDateString())->addDay()->toDateString());
        };
    }

    /**
     * Flatten every source into one comparable list for a single date.
     *
     * @param  array<string, mixed>  $data
     * @return list<array{kind: string, roomId: ?string, roomName: ?string, therapistId: ?string, therapistName: ?string, startMin: int, endMin: int, side: ConflictSide}>
     */
    private static function occurrences(array $data, Carbon $date): array
    {
        $day = $date->toDateString();
        $occurrences = [];

        foreach ($data['reservations'][$day] ?? [] as $reservation) {
            $occurrences[] = [
                'kind' => 'reservation',
                'roomId' => $reservation->room_id,
                'roomName' => $reservation->room?->name,
                'therapistId' => $reservation->therapist_id,
                'therapistName' => $reservation->therapist?->user?->name,
                'startMin' => Slot::toMinutes($reservation->start_time),
                'endMin' => Slot::toMinutes($reservation->end_time),
                'side' => ConflictSide::forReservation($reservation),
            ];
        }

        foreach ($data['lessons'][$day] ?? [] as $lesson) {
            $therapistId = $data['profileIdByUser'][$lesson->instructor_id] ?? null;

            $occurrences[] = [
                'kind' => 'lesson',
                'roomId' => $lesson->room_id,
                'roomName' => $lesson->room?->name,
                'therapistId' => $therapistId,
                'therapistName' => $lesson->instructor?->name,
                'startMin' => Slot::toMinutes($lesson->start_time),
                'endMin' => Slot::toMinutes($lesson->end_time),
                'side' => ConflictSide::forLesson($lesson, $therapistId),
            ];
        }

        foreach ($data['workBlocks'][$day] ?? [] as $block) {
            $occurrences[] = [
                'kind' => 'workBlock',
                'roomId' => $block->room_id,
                'roomName' => $block->room?->name,
                'therapistId' => $block->therapist_id,
                'therapistName' => $block->therapist?->user?->name,
                'startMin' => Slot::toMinutes($block->start_time),
                'endMin' => Slot::toMinutes($block->end_time),
                'side' => ConflictSide::forWorkBlock($block),
            ];
        }

        foreach (RoomBlockingIntervals::forDate($data['blockings'], $date) as $occurrence) {
            $blocking = $occurrence['blocking'];

            $occurrences[] = [
                'kind' => 'blocking',
                'roomId' => $occurrence['roomId'],
                'roomName' => $blocking->room?->name,
                'therapistId' => null,
                'therapistName' => null,
                'startMin' => $occurrence['startMin'],
                'endMin' => $occurrence['endMin'],
                'side' => ConflictSide::forBlocking($blocking, $day, $occurrence['startMin'], $occurrence['endMin']),
            ];
        }

        return $occurrences;
    }

    /**
     * Sweep one dimension: group by the contested room or person, then compare
     * each occurrence with the later ones until they can no longer reach it.
     * Touching intervals (one ends exactly when the next starts) are not a
     * conflict.
     *
     * @param  list<array<string, mixed>>  $occurrences
     * @return list<Conflict>
     */
    private static function pairs(array $occurrences, string $dimension, string $date): array
    {
        $idKey = $dimension === 'room' ? 'roomId' : 'therapistId';
        $nameKey = $dimension === 'room' ? 'roomName' : 'therapistName';
        $fallback = $dimension === 'room' ? 'Místnost' : 'Terapeut';

        $groups = [];
        foreach ($occurrences as $occurrence) {
            if ($occurrence[$idKey] === null) {
                continue;
            }

            $groups[$occurrence[$idKey]][] = $occurrence;
        }

        $conflicts = [];

        foreach ($groups as $group) {
            usort($group, fn (array $a, array $b): int => $a['startMin'] <=> $b['startMin']);
            $count = count($group);

            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    // Start-sorted, so once one starts too late every later one does.
                    if ($group[$j]['startMin'] >= $group[$i]['endMin']) {
                        break;
                    }

                    $kinds = [$group[$i]['kind'], $group[$j]['kind']];
                    sort($kinds);
                    $rule = self::RULES[$dimension][implode('|', $kinds)] ?? null;

                    if ($rule === null) {
                        continue;
                    }

                    [$severity, $title] = $rule;

                    $conflicts[] = new Conflict(
                        type: $dimension,
                        severity: $severity,
                        title: $title,
                        shared: $group[$i][$nameKey] ?? $group[$j][$nameKey] ?? $fallback,
                        date: $date,
                        a: $group[$i]['side'],
                        b: $group[$j]['side'],
                    );
                }
            }
        }

        return $conflicts;
    }
}
