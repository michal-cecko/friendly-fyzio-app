<?php

namespace App\Support;

use App\Models\StaffProfile;
use App\Models\TherapistWorkBlock;
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
     * Source: the materialized work blocks on that date. Passing $roomId
     * restricts the working time to that room.
     *
     * @param  array<int, string>  $therapistIds
     */
    public function availableMinutes(CarbonInterface $date, array $therapistIds = [], ?string $roomId = null): int
    {
        $therapistIds = $this->resolveTherapistIds($therapistIds);

        if ($therapistIds === []) {
            return 0;
        }

        return (int) TherapistWorkBlock::query()
            ->whereIn('therapist_id', $therapistIds)
            ->whereDate('work_date', $date)
            ->when($roomId, fn (Builder $query): Builder => $query->where('room_id', $roomId))
            ->get(['start_time', 'end_time'])
            ->sum(fn (TherapistWorkBlock $row): int => $this->minutesBetween($row->start_time, $row->end_time));
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

        return StaffProfile::query()->pluck('id')->all();
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
