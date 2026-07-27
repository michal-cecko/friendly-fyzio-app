<?php

namespace App\Support\Reservations;

use App\Enums\DayOfWeek;
use App\Models\RoomBlocking;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * One reading of the two shapes a {@see RoomBlocking} can take — a recurring
 * weekday rule, or a one-off `start_at`/`end_at` range that may span midnight —
 * resolved onto a concrete date as minute-of-day intervals.
 *
 * Shared by the slot engine ({@see ReservationSlots}), which subtracts these from
 * a therapist's work blocks, and by {@see ConflictFinder}, which reports what
 * they overlap. Keeping both on the same interpretation is the point: a blocking
 * the engine silently removes must never surface as a conflict the engine
 * doesn't know about, and vice versa.
 */
final class RoomBlockingIntervals
{
    /**
     * Blockings that could possibly fall inside the inclusive date range: every
     * recurring row (their weekday still has to match per date), plus one-off
     * rows whose range overlaps the window.
     *
     * @return Builder<RoomBlocking>
     */
    public static function inRange(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return RoomBlocking::query()
            ->where(fn (Builder $query): Builder => $query
                ->where('is_recurring', true)
                ->orWhere(fn (Builder $inner): Builder => $inner
                    ->where('is_recurring', false)
                    ->where('start_at', '<', $to->copy()->endOfDay())
                    ->where('end_at', '>', $from->copy()->startOfDay())));
    }

    /**
     * The occurrences of the given blockings on one date, retaining the model so
     * callers can label them. Recurring rows are matched on weekday + week
     * parity; one-off rows are clipped to the day, so one spanning midnight
     * yields an occurrence on each day it touches.
     *
     * @param  Collection<int, RoomBlocking>  $blockings
     * @return list<array{blocking: RoomBlocking, roomId: string, startMin: int, endMin: int}>
     */
    public static function forDate(Collection $blockings, CarbonInterface $date): array
    {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->endOfDay();
        $occurrences = [];

        foreach ($blockings as $blocking) {
            if ($blocking->is_recurring) {
                if ($blocking->day_of_week !== DayOfWeek::fromCarbon($date) || ! $blocking->week_type->matchesDate($date)) {
                    continue;
                }

                $startMin = Slot::toMinutes($blocking->start_time);
                $endMin = Slot::toMinutes($blocking->end_time);
            } else {
                if ($blocking->start_at === null || $blocking->end_at === null
                    || $blocking->end_at->lessThanOrEqualTo($dayStart) || $blocking->start_at->greaterThanOrEqualTo($dayEnd)) {
                    continue;
                }

                $startMin = $blocking->start_at->lessThanOrEqualTo($dayStart) ? 0 : $blocking->start_at->hour * 60 + $blocking->start_at->minute;
                $endMin = $blocking->end_at->greaterThanOrEqualTo($dayEnd) ? 24 * 60 : $blocking->end_at->hour * 60 + $blocking->end_at->minute;
            }

            $occurrences[] = [
                'blocking' => $blocking,
                'roomId' => (string) $blocking->room_id,
                'startMin' => $startMin,
                'endMin' => $endMin,
            ];
        }

        return $occurrences;
    }

    /**
     * The same occurrences folded into busy minute-intervals per room, the shape
     * the slot engine subtracts.
     *
     * @param  Collection<int, RoomBlocking>  $blockings
     * @return array<string, array<int, array{0: int, 1: int}>>
     */
    public static function byRoom(Collection $blockings, CarbonInterface $date): array
    {
        $byRoom = [];

        foreach (self::forDate($blockings, $date) as $occurrence) {
            $byRoom[$occurrence['roomId']][] = [$occurrence['startMin'], $occurrence['endMin']];
        }

        return $byRoom;
    }
}
