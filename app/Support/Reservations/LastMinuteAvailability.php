<?php

namespace App\Support\Reservations;

use App\Models\StaffProfile;
use App\Support\Avatar;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Real "last-minute" openings for the homepage brick: therapists with at least
 * one free slot today or tomorrow, each carrying their bookable services (shown
 * as tags) and their earliest free start times per day (deep-linked into the
 * wizard). The homepage renders this on every visit, so the computed result is
 * cached for a few minutes; the wizard remains the source of truth on click.
 *
 * @phpstan-type DaySlots array{date: string, times: array<int, string>}
 * @phpstan-type Opening array{id: string, name: string, initials: string, photo: ?string, title: ?string, permalink: ?string, services: array<int, string>, days: array<int, DaySlots>}
 */
class LastMinuteAvailability
{
    private const CACHE_KEY = 'brick.last-minute.openings';

    private const CACHE_TTL_SECONDS = 300;

    private const MAX_SLOTS_PER_DAY = 4;

    public function __construct(private readonly ReservationSlots $slots) {}

    /**
     * @return array<int, Opening>
     */
    public static function cached(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, fn (): array => app(self::class)->compute());
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<int, Opening>
     */
    public function compute(): array
    {
        $today = Carbon::today();
        $days = [$today, $today->copy()->addDay()];

        return StaffProfile::query()
            ->with(['user', 'services' => fn ($query) => $query->bookable()->orderBy('name')])
            ->whereHas('services', fn ($query) => $query->bookable())
            ->get()
            ->map(fn (StaffProfile $therapist): ?array => $this->forTherapist($therapist, $days))
            ->filter()
            ->sortBy('sortKey')
            ->map(fn (array $opening): array => collect($opening)->except('sortKey')->all())
            ->values()
            ->all();
    }

    /**
     * @param  array<int, Carbon>  $days
     * @return Opening|null null when the therapist has no free slot in the window
     */
    private function forTherapist(StaffProfile $therapist, array $days): ?array
    {
        $byDay = [];

        foreach ($days as $day) {
            $starts = [];

            foreach ($therapist->services as $service) {
                foreach ($this->slots->availableTimes($service, $day, $therapist->id) as $slot) {
                    $starts[$slot->start()] = true;
                }
            }

            if ($starts !== []) {
                ksort($starts);

                $byDay[] = [
                    'date' => $day->toDateString(),
                    'times' => array_slice(array_keys($starts), 0, self::MAX_SLOTS_PER_DAY),
                ];
            }
        }

        if ($byDay === []) {
            return null;
        }

        $name = $therapist->user?->full_name ?? '';
        $clickable = $therapist->isPublished() && filled($therapist->slug);

        return [
            'id' => $therapist->id,
            'slug' => $therapist->slug,
            'name' => $name,
            'initials' => Avatar::initials($name),
            'photo' => $therapist->photo,
            'title' => $therapist->title,
            'permalink' => $clickable ? $therapist->permalink : null,
            'services' => $therapist->services->pluck('name')->all(),
            'days' => $byDay,
            'sortKey' => $byDay[0]['date'].$byDay[0]['times'][0],
        ];
    }
}
