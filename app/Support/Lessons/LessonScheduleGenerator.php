<?php

namespace App\Support\Lessons;

use App\Enums\DayOfWeek;
use App\Models\CourseSeries;
use App\Models\Lesson;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Materializes a série's recurring schedule (weekdays + time + room, stored on
 * the série itself) into concrete {@see Lesson} rows between its start_date and
 * end_date.
 *
 * Stateless (Octane-safe): every call resolves its own data, nothing is cached
 * on the instance — the same contract as {@see App\Support\WorkBlocks\WorkBlockGenerator},
 * whose single-weekday occurrenceDates() this generalises to several weekdays.
 *
 * Generation is idempotent and additive: a date that already carries a lesson is
 * skipped, and nothing existing is ever rewritten or removed. Re-running after
 * extending the série's end_date fills in only the new dates.
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
     * Every date in [from, until] falling on any of the given weekdays, ascending
     * and deduplicated. An empty day list yields no dates.
     *
     * @param  array<int, DayOfWeek>  $days
     * @return array<int, CarbonImmutable>
     */
    public function occurrenceDates(array $days, CarbonInterface $from, CarbonInterface $until): array
    {
        $from = CarbonImmutable::parse($from->toDateString());
        $until = CarbonImmutable::parse($until->toDateString());

        $dates = [];

        foreach ($days as $day) {
            $cursor = $from;

            while (DayOfWeek::fromCarbon($cursor) !== $day) {
                $cursor = $cursor->addDay();
            }

            while ($cursor->lessThanOrEqualTo($until)) {
                $dates[$cursor->toDateString()] = $cursor;
                $cursor = $cursor->addWeek();
            }
        }

        ksort($dates);

        return array_values($dates);
    }

    /**
     * Create the série's missing lessons. Dates that already have one — including
     * a soft-deleted one — are counted as skipped and left alone: a session staff
     * deliberately cancelled must not come back on the next run.
     *
     * @return array{created: int, skipped: int, capped: bool}
     */
    public function generate(CourseSeries $series): array
    {
        $planned = $this->plannedDates($series);
        $missing = $this->missingDates($series);
        $instructor = $series->leadInstructor();

        // Dates the schedule wanted that already carry a lesson (live or cancelled).
        $skipped = count($planned) - count($missing);

        if ($missing === [] || $instructor === null) {
            return ['created' => 0, 'skipped' => $skipped, 'capped' => false];
        }

        $capped = count($missing) > self::MAX_LESSONS;
        $create = array_slice($missing, 0, self::MAX_LESSONS);

        DB::transaction(function () use ($series, $create, $instructor): void {
            $startTime = $this->time($series->start_time);
            $endTime = $this->time($series->end_time);

            foreach ($create as $date) {
                // create(), never a bulk insert: LessonObserver builds the attendance
                // roster for the série's participants, and Lesson is Auditable.
                Lesson::query()->create([
                    'series_id' => $series->getKey(),
                    'instructor_id' => $instructor->getKey(),
                    'room_id' => $series->room_id,
                    'lesson_date' => $date->toDateString(),
                    'start_time' => $startTime,
                    'end_time' => $endTime,
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
     * Every date the schedule calls for, whether or not a lesson exists yet.
     *
     * @return array<int, CarbonImmutable>
     */
    public function plannedDates(CourseSeries $series): array
    {
        if (! $series->hasLessonSchedule() || $series->leadInstructor() === null) {
            return [];
        }

        return $this->occurrenceDates($series->scheduleDays(), $series->start_date, $series->end_date);
    }

    /**
     * The scheduled dates with no lesson on them yet — what a run would create.
     *
     * @return array<int, CarbonImmutable>
     */
    public function missingDates(CourseSeries $series): array
    {
        $planned = $this->plannedDates($series);

        if ($planned === []) {
            return [];
        }

        $taken = $this->existingDates($series);

        return array_values(array_filter(
            $planned,
            fn (CarbonImmutable $date): bool => ! in_array($date->toDateString(), $taken, true),
        ));
    }

    /**
     * Dates of the série's lessons, soft-deleted ones included — the whole point
     * of withTrashed() here is that a cancelled lesson keeps its date occupied.
     *
     * @return array<int, string>
     */
    protected function existingDates(CourseSeries $series): array
    {
        return $series->lessons()
            ->withTrashed()
            ->pluck('lesson_date')
            ->map(fn (mixed $date): string => CarbonImmutable::parse($date)->toDateString())
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Normalise to the H:i:s the lesson form writes — the série's TimePicker
     * submits H:i, and the column is an uncast raw string on both models.
     */
    protected function time(string $value): string
    {
        return CarbonImmutable::parse($value)->format('H:i:s');
    }
}
