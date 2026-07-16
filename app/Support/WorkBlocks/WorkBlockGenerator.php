<?php

namespace App\Support\WorkBlocks;

use App\Enums\DayOfWeek;
use App\Enums\WeekType;
use App\Models\TherapistWorkBlock;
use App\Models\TherapistWorkBlockSeries;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Materializes TherapistWorkBlockSeries recurrence rules into concrete
 * TherapistWorkBlock rows.
 *
 * Stateless (Octane-safe): every call resolves its own data, nothing is
 * cached on the instance. Generation only ever appends dates after the
 * series' generated_until cursor, so occurrences the admin deleted (vacation)
 * or edited are never recreated.
 */
class WorkBlockGenerator
{
    /**
     * How far ahead open-ended series are materialized. Must stay comfortably
     * beyond the public booking window (Settings::bookingWindowDays).
     */
    public const HORIZON_WEEKS = 26;

    public static function horizon(): CarbonImmutable
    {
        return CarbonImmutable::today()->addWeeks(self::HORIZON_WEEKS);
    }

    /**
     * All dates in [from, until] falling on the given weekday and matching the
     * week parity.
     *
     * @return array<int, CarbonImmutable>
     */
    public function occurrenceDates(DayOfWeek $day, WeekType $weekType, CarbonInterface $from, CarbonInterface $until): array
    {
        $from = CarbonImmutable::parse($from->toDateString());
        $until = CarbonImmutable::parse($until->toDateString());

        $cursor = $from;

        while (DayOfWeek::fromCarbon($cursor) !== $day) {
            $cursor = $cursor->addDay();
        }

        $dates = [];

        while ($cursor->lessThanOrEqualTo($until)) {
            if ($weekType->matchesDate($cursor)) {
                $dates[] = $cursor;
            }

            $cursor = $cursor->addWeek();
        }

        return $dates;
    }

    /**
     * Insert the series' missing work blocks up to $until (capped by ends_on),
     * skipping dates where the therapist already has an overlapping block, and
     * advance the generated_until cursor.
     *
     * @return array{created: int, skipped: int}
     */
    public function materialize(TherapistWorkBlockSeries $series, CarbonInterface $until): array
    {
        $until = CarbonImmutable::parse($until->toDateString());

        if ($series->ends_on !== null && $series->ends_on->lessThan($until)) {
            $until = CarbonImmutable::parse($series->ends_on->toDateString());
        }

        $from = CarbonImmutable::parse($series->generated_until->toDateString())->addDay();

        if ($series->starts_on->greaterThan($from)) {
            $from = CarbonImmutable::parse($series->starts_on->toDateString());
        }

        if ($from->greaterThan($until)) {
            return ['created' => 0, 'skipped' => 0];
        }

        $dates = $this->occurrenceDates($series->day_of_week, $series->week_type, $from, $until);

        $conflicts = $this->conflictingDates($series, $from, $until);

        $now = now();
        $rows = [];
        $skipped = 0;

        foreach ($dates as $date) {
            if (in_array($date->toDateString(), $conflicts, true)) {
                $skipped++;

                continue;
            }

            $rows[] = [
                'id' => (string) Str::uuid7(),
                'therapist_id' => $series->therapist_id,
                'room_id' => $series->room_id,
                'series_id' => $series->getKey(),
                'work_date' => $date->toDateString(),
                'start_time' => $series->start_time,
                'end_time' => $series->end_time,
                'note' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($series, $until, $rows): void {
            if ($rows !== []) {
                TherapistWorkBlock::query()->insert($rows);
            }

            $series->update(['generated_until' => $until->toDateString()]);
        });

        return ['created' => count($rows), 'skipped' => $skipped];
    }

    /**
     * Dates in [from, until] where the therapist already has a block
     * overlapping the series' time interval.
     *
     * @return array<int, string>
     */
    protected function conflictingDates(TherapistWorkBlockSeries $series, CarbonInterface $from, CarbonInterface $until): array
    {
        return TherapistWorkBlock::query()
            ->where('therapist_id', $series->therapist_id)
            ->whereBetween('work_date', [$from->toDateString(), $until->toDateString()])
            ->where('start_time', '<', $series->end_time)
            ->where('end_time', '>', $series->start_time)
            ->get(['work_date'])
            ->map(fn (TherapistWorkBlock $block): string => $block->work_date->toDateString())
            ->unique()
            ->values()
            ->all();
    }
}
