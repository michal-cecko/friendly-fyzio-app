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
     * @return array<int, array{day: string, start_time: string, end_time: string}>
     */
    public function toArray(): array
    {
        return array_map(fn (ScheduleSlot $slot): array => $slot->toArray(), $this->slots);
    }

    /**
     * "úterý a čtvrtek 17:30–18:30" — the full label, weekdays lowercase for
     * running text. Null while the rozvrh is empty, so callers can fall back to
     * a placeholder or hide the row entirely.
     */
    public function label(): ?string
    {
        return $this->render(withEndTime: true);
    }

    /**
     * "úterý a čtvrtek 17:30" — start time only, for the cramped offer card.
     */
    public function shortLabel(): ?string
    {
        return $this->render(withEndTime: false);
    }

    protected function render(bool $withEndTime): ?string
    {
        if ($this->isEmpty()) {
            return null;
        }

        return collect($this->slots)
            ->groupBy(fn (ScheduleSlot $slot): string => $withEndTime ? $slot->timeLabel() : $slot->startTime)
            ->map(fn (Collection $group, string $time): string => $this->days($group).' '.$time)
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
