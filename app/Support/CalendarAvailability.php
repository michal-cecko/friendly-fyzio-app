<?php

namespace App\Support;

use App\Enums\DayOfWeek;
use App\Enums\WeekType;
use App\Models\CalendarBlock;
use App\Models\TherapistNonstandardDate;
use App\Models\TherapistProfile;
use App\Models\TherapistWeeklySchedule;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Computes how much bookable working time therapists have on a given day.
 *
 * Stateless (Octane-safe): every call resolves its own data, nothing is cached
 * on the instance. Used by the calendar's day-summary panel to derive free time
 * and utilization for the selected day.
 */
class CalendarAvailability
{
    /**
     * Total bookable working minutes on a date, summed over the given therapists
     * (all therapists when the list is empty).
     *
     * Sources: recurring weekly schedules matching the date's weekday + week
     * parity, plus one-off non-standard working dates. Therapists fully covered
     * by a multi-day calendar block (vacation/sick) contribute nothing.
     *
     * Passing $roomId restricts the working time to that room (both weekly and
     * non-standard schedules carry a room_id); the vacation/sick block check
     * stays therapist-level.
     *
     * @param  array<int, string>  $therapistIds
     */
    public function availableMinutes(CarbonInterface $date, array $therapistIds = [], ?string $roomId = null): int
    {
        $therapistIds = $this->resolveTherapistIds($therapistIds);

        if ($therapistIds === []) {
            return 0;
        }

        $active = array_values(array_diff($therapistIds, $this->blockedTherapistIds($date, $therapistIds)));

        if ($active === []) {
            return 0;
        }

        return $this->weeklyMinutes($date, $active, $roomId) + $this->nonstandardMinutes($date, $active, $roomId);
    }

    /**
     * @param  array<int, string>  $therapistIds
     * @return array<int, string>
     */
    protected function resolveTherapistIds(array $therapistIds): array
    {
        if ($therapistIds !== []) {
            return array_values($therapistIds);
        }

        return TherapistProfile::query()->pluck('id')->all();
    }

    /**
     * Therapists whose whole day is removed by a multi-day calendar block.
     *
     * @param  array<int, string>  $therapistIds
     * @return array<int, string>
     */
    protected function blockedTherapistIds(CarbonInterface $date, array $therapistIds): array
    {
        return CalendarBlock::query()
            ->whereIn('therapist_id', $therapistIds)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->pluck('therapist_id')
            ->all();
    }

    /**
     * @param  array<int, string>  $therapistIds
     */
    protected function weeklyMinutes(CarbonInterface $date, array $therapistIds, ?string $roomId = null): int
    {
        return (int) TherapistWeeklySchedule::query()
            ->whereIn('therapist_id', $therapistIds)
            ->where('day_of_week', DayOfWeek::fromCarbon($date)->value)
            ->whereIn('week_type', [WeekType::All->value, WeekType::forDate($date)->value])
            ->when($roomId, fn (Builder $query): Builder => $query->where('room_id', $roomId))
            ->get(['start_time', 'end_time'])
            ->sum(fn (TherapistWeeklySchedule $row): int => $this->minutesBetween($row->start_time, $row->end_time));
    }

    /**
     * @param  array<int, string>  $therapistIds
     */
    protected function nonstandardMinutes(CarbonInterface $date, array $therapistIds, ?string $roomId = null): int
    {
        return (int) TherapistNonstandardDate::query()
            ->whereIn('therapist_id', $therapistIds)
            ->whereDate('work_date', $date)
            ->when($roomId, fn (Builder $query): Builder => $query->where('room_id', $roomId))
            ->get(['start_time', 'end_time'])
            ->sum(fn (TherapistNonstandardDate $row): int => $this->minutesBetween($row->start_time, $row->end_time));
    }

    protected function minutesBetween(?string $start, ?string $end): int
    {
        if (blank($start) || blank($end)) {
            return 0;
        }

        return max(0, $this->toMinutes($end) - $this->toMinutes($start));
    }

    /**
     * Minutes since midnight for a `H:i` or `H:i:s` time string.
     */
    protected function toMinutes(string $time): int
    {
        [$hours, $minutes] = array_pad(explode(':', $time), 2, '0');

        return ((int) $hours) * 60 + (int) $minutes;
    }
}
