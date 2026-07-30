<?php

namespace App\Support\Lessons;

use Illuminate\Support\Collection;

/**
 * A série's rozvrh — the list of {@see ScheduleSlot}s it meets on, sorted
 * pondělí → neděle, and the presenter that turns them into the one-liner shown
 * to clients ("úterý a čtvrtek 17:30–18:30").
 *
 * Slots sharing a time collapse into one group, so a twice-a-week course reads
 * as one phrase instead of two; slots with different times stay separate
 * ("středa 9:00–10:00, čtvrtek 10:30–11:30").
 */
final readonly class SeriesSchedule
{
    /**
     * @param  array<int, ScheduleSlot>  $slots
     */
    private function __construct(private array $slots) {}

    /**
     * Parse the stored JSON rozvrh. Unusable rows are dropped, duplicates
     * collapse, and the result is sorted — callers never have to defend
     * themselves against what is in the column.
     *
     * @param  array<mixed>|null  $raw
     */
    public static function fromArray(?array $raw): self
    {
        return self::fromSlots(
            collect($raw ?? [])
                ->filter(fn (mixed $row): bool => is_array($row))
                ->map(fn (array $row): ?ScheduleSlot => ScheduleSlot::fromArray($row))
                ->filter()
                ->all()
        );
    }

    /**
     * @param  array<int, ScheduleSlot>  $slots
     */
    public static function fromSlots(array $slots): self
    {
        return new self(
            collect($slots)
                ->unique(fn (ScheduleSlot $slot): string => $slot->key())
                ->sortBy(fn (ScheduleSlot $slot): string => $slot->sortKey())
                ->values()
                ->all()
        );
    }

    /**
     * @return array<int, ScheduleSlot>
     */
    public function slots(): array
    {
        return $this->slots;
    }

    public function isEmpty(): bool
    {
        return $this->slots === [];
    }

    /**
     * @return array<int, array{day: string, start_time: string, end_time: string, room_id: string|null}>
     */
    public function toArray(): array
    {
        return array_map(fn (ScheduleSlot $slot): array => $slot->toArray(), $this->slots);
    }

    /**
     * The distinct rooms the rozvrh names, so a caller resolves their names in a
     * single query instead of one per slot.
     *
     * @return array<int, string>
     */
    public function roomIds(): array
    {
        return collect($this->slots)
            ->map(fn (ScheduleSlot $slot): ?string => $slot->roomId)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Whether every slot names a room — what {@see LessonScheduleGenerator} needs
     * before it can place a lesson. An empty rozvrh trivially passes; callers pair
     * this with {@see isEmpty()}.
     */
    public function everySlotHasRoom(): bool
    {
        return collect($this->slots)->every(fn (ScheduleSlot $slot): bool => $slot->roomId !== null);
    }

    /**
     * "úterý a čtvrtek 17:30–18:30" — the full label, weekdays lowercase for
     * running text. Null while the rozvrh is empty, so callers can fall back to
     * a placeholder or hide the row entirely.
     */
    public function label(): ?string
    {
        if ($this->isEmpty()) {
            return null;
        }

        return collect($this->slots)
            ->groupBy(fn (ScheduleSlot $slot): string => $slot->timeLabel())
            ->map(fn (Collection $group, string $time): string => $this->days($group).' '.$time)
            ->implode(', ');
    }

    /**
     * The same line with the room in it — the staff-side label; clients get the
     * place on its own row, so {@see label()} stays room-free.
     *
     * A série meeting in one room names it once at the end ("pondělí a středa
     * 17:00–18:00 · Velká tělocvična"). Only when the rooms differ does the line
     * split per room, so staff see which day meets where ("pondělí 17:00–18:00 ·
     * Velká tělocvična, středa 9:00–10:00 · Malá tělocvična"). Slots whose room is
     * unknown — not filled in, or since deleted — read exactly as {@see label()}.
     *
     * @param  array<string, string>  $roomNames  room id => name
     */
    public function labelWithRooms(array $roomNames): ?string
    {
        if ($this->isEmpty()) {
            return null;
        }

        $rooms = $this->roomIds();

        if ($this->everySlotHasRoom() && count($rooms) === 1) {
            return implode(' · ', array_filter([$this->label(), $roomNames[$rooms[0]] ?? null]));
        }

        return collect($this->slots)
            ->groupBy(fn (ScheduleSlot $slot): string => $slot->timeLabel().'|'.($slot->roomId ?? ''))
            ->map(function (Collection $group) use ($roomNames): string {
                /** @var ScheduleSlot $first */
                $first = $group->first();
                $room = $first->roomId !== null ? ($roomNames[$first->roomId] ?? null) : null;

                return implode(' · ', array_filter([
                    $this->days($group).' '.$first->timeLabel(),
                    $room,
                ]));
            })
            ->implode(', ');
    }

    /**
     * "út 15:00, st 10:00, čt 15:00" — the offer card's one line: every slot on
     * its own, abbreviated weekday and start time only. Days sharing a time are
     * deliberately *not* grouped here; the card reads as a plain list.
     */
    public function shortLabel(): ?string
    {
        if ($this->isEmpty()) {
            return null;
        }

        return collect($this->slots)
            ->map(fn (ScheduleSlot $slot): string => $slot->day->abbreviation().' '.$slot->startTime)
            ->implode(', ');
    }

    /**
     * "úterý a čtvrtek" — two days joined with "a", three or more with commas
     * and a final "a".
     *
     * @param  Collection<int, ScheduleSlot>  $group
     */
    protected function days(Collection $group): string
    {
        $days = $group
            ->map(fn (ScheduleSlot $slot): string => $slot->day->lowerLabel())
            ->unique()
            ->values()
            ->all();

        $last = array_pop($days);

        return $days === [] ? $last : implode(', ', $days).' a '.$last;
    }
}
