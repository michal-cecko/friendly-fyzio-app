<?php

namespace App\Support\Lessons;

use App\Enums\DayOfWeek;
use App\Models\CourseSeries;
use App\Models\Lesson;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Materializes a série's recurring rozvrh (a list of slots stored on the série
 * itself, each with its own weekday, time and room) into concrete {@see Lesson}
 * rows between its start_date and end_date.
 *
 * Stateless (Octane-safe): every call resolves its own data, nothing is cached
 * on the instance — the same contract as {@see App\Support\WorkBlocks\WorkBlockGenerator},
 * whose single-weekday occurrenceDates() this generalises to several slots.
 *
 * Generation is idempotent and additive: a date+time that already carries a
 * lesson is skipped, and nothing existing is ever rewritten or removed.
 * Re-running after extending the série's end_date fills in only the new dates.
 */
class LessonScheduleGenerator
{
    /**
     * Ceiling on one run. A mistyped end_date (2036 instead of 2026) would
     * otherwise create hundreds of lessons and a roster row per participant per
     * lesson; stopping at a visible number is recoverable, a runaway insert is
     * not.
     */
    public const MAX_LESSONS = 200;

    /**
     * Every date in [from, until] falling on the given weekday, ascending.
     *
     * @return array<int, CarbonImmutable>
     */
    public function occurrenceDates(DayOfWeek $day, CarbonInterface $from, CarbonInterface $until): array
    {
        $until = CarbonImmutable::parse($until->toDateString());
        $cursor = CarbonImmutable::parse($from->toDateString());

        while (DayOfWeek::fromCarbon($cursor) !== $day) {
            $cursor = $cursor->addDay();
        }

        $dates = [];

        while ($cursor->lessThanOrEqualTo($until)) {
            $dates[] = $cursor;
            $cursor = $cursor->addWeek();
        }

        return $dates;
    }

    /**
     * Create the série's missing lessons. Sessions that already exist — including
     * soft-deleted ones — are counted as skipped and left alone: a session staff
     * deliberately cancelled must not come back on the next run.
     *
     * @return array{created: int, skipped: int, capped: bool}
     */
    public function generate(CourseSeries $series): array
    {
        $planned = $this->plannedSessions($series);
        $missing = $this->missingSessions($series);
        $instructor = $series->leadInstructor();

        // Sessions the rozvrh wanted that already exist (live or cancelled).
        $skipped = count($planned) - count($missing);

        if ($missing === [] || $instructor === null) {
            return ['created' => 0, 'skipped' => $skipped, 'capped' => false];
        }

        $capped = count($missing) > self::MAX_LESSONS;
        $create = array_slice($missing, 0, self::MAX_LESSONS);

        DB::transaction(function () use ($series, $create, $instructor): void {
            foreach ($create as ['date' => $date, 'slot' => $slot]) {
                // create(), never a bulk insert: LessonObserver builds the attendance
                // roster for the série's participants, and Lesson is Auditable.
                Lesson::query()->create([
                    'series_id' => $series->getKey(),
                    'instructor_id' => $instructor->getKey(),
                    // Every slot carries its own room, so a série can run pondělí
                    // in one room and středa in another.
                    'room_id' => $slot->roomId,
                    'lesson_date' => $date->toDateString(),
                    'start_time' => $this->time($slot->startTime),
                    'end_time' => $this->time($slot->endTime),
                ]);
            }
        });

        return [
            'created' => count($create),
            'skipped' => $skipped,
            'capped' => $capped,
        ];
    }

    /**
     * Every session the rozvrh calls for, whether or not a lesson exists yet,
     * ascending. A série meeting twice on one day (a morning and an evening
     * group) yields two sessions on that date — hence the slot next to the date.
     *
     * @return array<int, array{date: CarbonImmutable, slot: ScheduleSlot}>
     */
    public function plannedSessions(CourseSeries $series): array
    {
        if (! $series->hasLessonSchedule() || $series->leadInstructor() === null) {
            return [];
        }

        $sessions = [];

        foreach ($series->weeklySchedule()->slots() as $slot) {
            foreach ($this->occurrenceDates($slot->day, $series->start_date, $series->end_date) as $date) {
                $sessions[$this->sessionKey($date, $slot->startTime)] = ['date' => $date, 'slot' => $slot];
            }
        }

        ksort($sessions);

        return array_values($sessions);
    }

    /**
     * The scheduled sessions with no lesson on them yet — what a run would create.
     *
     * A session counts as taken when a lesson sits on its date at its time. On a
     * weekday the rozvrh visits only once, any lesson on that date takes it,
     * whatever its time: staff moving a session an hour later must not make the
     * generator re-create the original. A weekday with several slots (a morning
     * and an evening group) has to match on the time as well, otherwise the
     * second group could never be generated.
     *
     * @return array<int, array{date: CarbonImmutable, slot: ScheduleSlot}>
     */
    public function missingSessions(CourseSeries $series): array
    {
        $planned = $this->plannedSessions($series);

        if ($planned === []) {
            return [];
        }

        $slotsPerDay = collect($series->weeklySchedule()->slots())
            ->countBy(fn (ScheduleSlot $slot): string => $slot->day->value);

        $existing = $this->existingSessions($series);

        return array_values(array_filter($planned, function (array $session) use ($slotsPerDay, $existing): bool {
            $date = $session['date']->toDateString();

            $taken = $slotsPerDay->get($session['slot']->day->value, 1) > 1
                ? in_array($this->sessionKey($session['date'], $session['slot']->startTime), $existing['sessions'], true)
                : in_array($date, $existing['dates'], true);

            return ! $taken;
        }));
    }

    /**
     * The dates and date+time keys of the série's lessons, soft-deleted ones
     * included — the whole point of withTrashed() here is that a cancelled lesson
     * keeps its slot occupied.
     *
     * @return array{dates: array<int, string>, sessions: array<int, string>}
     */
    protected function existingSessions(CourseSeries $series): array
    {
        $lessons = $series->lessons()
            ->withTrashed()
            ->get(['lesson_date', 'start_time']);

        return [
            'dates' => $lessons
                ->map(fn (Lesson $lesson): string => CarbonImmutable::parse($lesson->lesson_date)->toDateString())
                ->unique()
                ->values()
                ->all(),
            'sessions' => $lessons
                ->map(fn (Lesson $lesson): string => $this->sessionKey(
                    CarbonImmutable::parse($lesson->lesson_date),
                    (string) $lesson->start_time,
                ))
                ->unique()
                ->values()
                ->all(),
        ];
    }

    /**
     * "2026-09-15 17:30" — one session of the série, date and time normalised so
     * the same slot always produces the same key. Doubles as the sort key.
     */
    protected function sessionKey(CarbonInterface $date, string $startTime): string
    {
        return $date->toDateString().' '.CarbonImmutable::parse($startTime)->format('H:i');
    }

    /**
     * Normalise to the H:i:s the lesson form writes — a rozvrh slot holds H:i,
     * and the column is an uncast raw string on both models.
     */
    protected function time(string $value): string
    {
        return CarbonImmutable::parse($value)->format('H:i:s');
    }
}
