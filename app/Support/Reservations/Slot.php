<?php

namespace App\Support\Reservations;

/**
 * An offerable booking slot resolved by {@see ReservationSlots}.
 *
 * Times are wall-clock minutes since midnight on a single local date; the helpers
 * format them back to the `H:i` strings the UI and the reservations table use.
 */
final class Slot
{
    public function __construct(
        public readonly string $date,            // 'Y-m-d'
        public readonly int $startMin,
        public readonly int $endMin,
        public readonly string $therapistId,
        public readonly string $roomId,
        public readonly int $durationMinutes,
    ) {}

    public function start(): string
    {
        return self::toTime($this->startMin);
    }

    public function end(): string
    {
        return self::toTime($this->endMin);
    }

    public static function toTime(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    public static function toMinutes(string $time): int
    {
        [$hours, $minutes] = array_pad(explode(':', $time), 2, '0');

        return ((int) $hours) * 60 + (int) $minutes;
    }
}
