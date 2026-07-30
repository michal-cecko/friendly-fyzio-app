<?php

namespace App\Support\Lessons;

use App\Enums\DayOfWeek;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * One recurring meeting of a série — "Úterý 17:30–18:30". A série's rozvrh is a
 * list of these rather than a day list sharing one time, so a course meeting
 * twice a week in different times (středa 9:00, čtvrtek 10:30) is expressible.
 *
 * Each slot also carries the room it meets in, so a série can run pondělí in one
 * room and středa in another; the room is optional, since a rozvrh may be typed
 * before the rooms are settled ({@see LessonScheduleGenerator} then refuses to
 * run — a lesson cannot be saved without one).
 *
 * Times are normalised to H:i, the shape the TimePicker submits; the lesson
 * columns are uncast raw strings, so {@see LessonScheduleGenerator} widens them
 * back to H:i:s on write.
 */
final readonly class ScheduleSlot
{
    public function __construct(
        public DayOfWeek $day,
        public string $startTime,
        public string $endTime,
        public ?string $roomId = null,
    ) {}

    /**
     * Build from one stored rozvrh row, or null when the row is unusable. An
     * unknown weekday or a missing time is dropped rather than thrown on — a
     * rozvrh is a convenience, and one stale row must not break a detail page.
     * A missing room is not a defect: the slot still states when the série meets.
     *
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): ?self
    {
        $day = is_string($row['day'] ?? null) ? DayOfWeek::tryFrom($row['day']) : null;
        $start = self::time($row['start_time'] ?? null);
        $end = self::time($row['end_time'] ?? null);
        $room = $row['room_id'] ?? null;

        if ($day === null || $start === null || $end === null) {
            return null;
        }

        return new self($day, $start, $end, is_string($room) && filled($room) ? $room : null);
    }

    /**
     * @return array{day: string, start_time: string, end_time: string, room_id: string|null}
     */
    public function toArray(): array
    {
        return [
            'day' => $this->day->value,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'room_id' => $this->roomId,
        ];
    }

    /**
     * "17:30–18:30" — en dash, the separator the rest of the app uses for time
     * ranges.
     */
    public function timeLabel(): string
    {
        return $this->startTime.'–'.$this->endTime;
    }

    /**
     * "Úterý 17:30–18:30" — the admin-side one-liner (repeater item label).
     */
    public function label(): string
    {
        return $this->day->getLabel().' '.$this->timeLabel();
    }

    /**
     * Identity of the recurring meeting: two rows with the same key are the same
     * slot and collapse into one.
     *
     * The room is deliberately not part of it — a série cannot meet in two rooms
     * at the same time, so two rows on one day and time are a duplicate whatever
     * rooms they name, and the first one's room wins.
     */
    public function key(): string
    {
        return $this->day->value.' '.$this->timeLabel();
    }

    /**
     * Sort key — weekday order first, then the time, so a rozvrh always reads
     * pondělí → neděle regardless of the order it was typed in.
     */
    public function sortKey(): string
    {
        return $this->day->order().' '.$this->timeLabel();
    }

    protected static function time(mixed $value): ?string
    {
        if (! is_string($value) || blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->format('H:i');
        } catch (Throwable) {
            return null;
        }
    }
}
