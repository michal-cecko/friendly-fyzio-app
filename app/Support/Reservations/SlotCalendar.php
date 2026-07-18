<?php

namespace App\Support\Reservations;

use Illuminate\Support\Carbon;

/**
 * Builds the Monday-first month grids the booking calendars render — shared by
 * the public wizard and the client zone's reschedule page so both stay in sync.
 */
class SlotCalendar
{
    /**
     * Every month between the two dates as a Monday-first grid of day cells.
     *
     * @param  array{available: array<int, string>, full: array<int, string>}  $availability
     * @return array<int, array{label: string, weeks: array<int, array<int, ?array{date: string, day: int, available: bool, today: bool, queue: ?string}>>}>
     */
    public static function months(Carbon $first, Carbon $last, array $availability): array
    {
        $available = array_flip($availability['available'] ?? []);
        $full = array_flip($availability['full'] ?? []);
        $today = Carbon::today();
        $months = [];

        for ($month = $first->copy()->startOfMonth(); $month->lte($last); $month->addMonth()) {
            $cursor = $month->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
            $end = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

            $weeks = [];
            while ($cursor->lte($end)) {
                $week = [];
                for ($day = 0; $day < 7; $day++) {
                    if ($cursor->month === $month->month) {
                        $ds = $cursor->toDateString();
                        $week[] = [
                            'date' => $ds,
                            'day' => $cursor->day,
                            'available' => isset($available[$ds]),
                            'today' => $cursor->isSameDay($today),
                            // 'full' = works that day but fully booked ("pořadník").
                            'queue' => isset($full[$ds]) ? 'full' : null,
                        ];
                    } else {
                        $week[] = null;
                    }
                    $cursor->addDay();
                }
                $weeks[] = $week;
            }

            $months[] = ['label' => $month->copy()->locale('cs')->isoFormat('MMMM YYYY'), 'weeks' => $weeks];
        }

        return $months;
    }

    /**
     * Index of the month holding the earliest available day — the month a
     * calendar should open on.
     *
     * @param  array<int, string>  $availableDays
     */
    public static function initialIndex(Carbon $first, array $availableDays): int
    {
        if ($availableDays === []) {
            return 0;
        }

        $earliest = Carbon::parse($availableDays[0])->startOfMonth();

        return max(0, $first->copy()->startOfMonth()->diffInMonths($earliest));
    }
}
