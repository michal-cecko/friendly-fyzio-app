<?php

namespace App\Support\Lessons;

use App\Enums\DayOfWeek;
use App\Models\CourseSeries;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Reads a série's recurring rozvrh back out of the lessons it already has —
 * used to backfill séries whose lessons were created before the rozvrh existed
 * (the historical import builds lessons directly).
 *
 * A slot has to repeat to count: a single lesson someone moved to another day
 * must not turn into a weekly term. When nothing repeats — a série of two
 * lessons, say — every distinct slot is kept, since there is nothing better to
 * infer from.
 */
class ScheduleFromLessons
{
    /**
     * How many lessons a slot needs before it is treated as recurring.
     */
    protected const MIN_OCCURRENCES = 2;

    public function forSeries(CourseSeries $series): SeriesSchedule
    {
        return $this->fromRows(
            $series->lessons()->get(['lesson_date', 'start_time', 'end_time', 'room_id'])
        );
    }

    /**
     * Slots are grouped on the room-free {@see ScheduleSlot::key()}, so the first
     * lesson of a recurring slot decides its room — a session someone moved
     * elsewhere once does not rewrite where the série usually meets.
     *
     * @param  iterable<int, object|array<string, mixed>>  $rows  anything carrying
     *                                                            lesson_date, start_time, end_time and room_id — models or raw DB rows
     */
    public function fromRows(iterable $rows): SeriesSchedule
    {
        $slots = collect($rows)
            ->map(fn (object|array $row): ?ScheduleSlot => $this->slot($row))
            ->filter()
            ->groupBy(fn (ScheduleSlot $slot): string => $slot->key());

        /** @var Collection<string, Collection<int, ScheduleSlot>> $recurring */
        $recurring = $slots->filter(fn (Collection $group): bool => $group->count() >= self::MIN_OCCURRENCES);

        return SeriesSchedule::fromSlots(
            ($recurring->isNotEmpty() ? $recurring : $slots)
                ->map(fn (Collection $group): ScheduleSlot => $group->first())
                ->values()
                ->all()
        );
    }

    /**
     * @param  object|array<string, mixed>  $row
     */
    protected function slot(object|array $row): ?ScheduleSlot
    {
        // Models expose attributes as properties, raw DB rows as stdClass ones,
        // and the migration may hand over plain arrays.
        $value = fn (string $key): mixed => is_array($row) ? ($row[$key] ?? null) : ($row->{$key} ?? null);

        $date = $value('lesson_date');

        if (blank($date)) {
            return null;
        }

        $start = $value('start_time');
        $end = $value('end_time');
        $room = $value('room_id');

        return ScheduleSlot::fromArray([
            'day' => DayOfWeek::fromCarbon(CarbonImmutable::parse($date))->value,
            'start_time' => is_string($start) ? $start : null,
            'end_time' => is_string($end) ? $end : null,
            'room_id' => is_string($room) ? $room : null,
        ]);
    }
}
