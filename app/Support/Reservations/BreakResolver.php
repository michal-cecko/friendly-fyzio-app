<?php

namespace App\Support\Reservations;

use App\Models\ServiceTherapist;
use App\Models\StaffProfile;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

/**
 * Answers one question: how long does a therapist rest after a given service?
 *
 * The rule, in one place:
 *
 *   service_therapists.break_blocks ?? staff_profiles.break_blocks
 *
 * — a therapist's profile carries their usual break, and an individual service
 * assignment may override it (a massage may need more than a consultation).
 * Both are counted in reservation blocks; {@see Settings::blockMinutes()} turns
 * them into the minutes everything else works in.
 *
 * Stateless across calls and Octane-safe (mirrors {@see ReservationSlots}):
 * instances are meant to be short-lived, and nothing is cached statically.
 */
class BreakResolver
{
    /** @var array<string, int>|null therapist id => default blocks */
    private ?array $defaults = null;

    /** @var array<string, int>|null user id => default blocks */
    private ?array $userDefaults = null;

    /** @var array<string, int>|null "therapistId|serviceId" => override blocks */
    private ?array $overrides = null;

    /**
     * Bulk path: both tables are loaded once per instance, so a caller resolving
     * a whole day's worth of therapists pays two queries rather than two per
     * booking. Use it when the answer is needed many times over.
     */
    public function minutesFor(?string $therapistId, ?string $serviceId): int
    {
        if ($therapistId === null) {
            return 0;
        }

        $blocks = $this->overrides()[$therapistId.'|'.$serviceId]
            ?? $this->defaults()[$therapistId]
            ?? 0;

        return $blocks * Settings::blockMinutes();
    }

    /**
     * The therapist's own default, ignoring service overrides — the break behind
     * work that is not a reservation, such as a course lesson they teach.
     */
    public function defaultMinutesFor(?string $therapistId): int
    {
        if ($therapistId === null) {
            return 0;
        }

        return ($this->defaults()[$therapistId] ?? 0) * Settings::blockMinutes();
    }

    /**
     * The same default, found through the user rather than their profile —
     * lessons name their lecturer as a user. Somebody who holds no staff profile
     * (an external lecturer renting the room) leaves no break behind them: they
     * are not resting between clients, they are simply done with the room.
     */
    public function defaultMinutesForUser(?string $userId): int
    {
        if ($userId === null) {
            return 0;
        }

        return ($this->userDefaults()[$userId] ?? 0) * Settings::blockMinutes();
    }

    /**
     * Single-write path: one query, never cached, so a reservation freezes the
     * break exactly as it stands the moment it is saved.
     */
    public static function freshMinutesFor(?string $therapistId, ?string $serviceId): int
    {
        if ($therapistId === null) {
            return 0;
        }

        $blocks = DB::table('staff_profiles')
            ->leftJoin('service_therapists', fn ($join) => $join
                ->on('service_therapists.therapist_id', '=', 'staff_profiles.id')
                ->where('service_therapists.service_id', '=', $serviceId))
            ->where('staff_profiles.id', $therapistId)
            ->value(DB::raw('coalesce(service_therapists.break_blocks, staff_profiles.break_blocks)'));

        return (int) $blocks * Settings::blockMinutes();
    }

    /**
     * @return array<string, int>
     */
    private function defaults(): array
    {
        return $this->defaults ??= StaffProfile::query()
            ->pluck('break_blocks', 'id')
            ->map(fn (mixed $blocks): int => (int) $blocks)
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function userDefaults(): array
    {
        return $this->userDefaults ??= StaffProfile::query()
            ->pluck('break_blocks', 'user_id')
            ->map(fn (mixed $blocks): int => (int) $blocks)
            ->all();
    }

    /**
     * Only real overrides are indexed — a null `break_blocks` means "inherit",
     * so leaving it out of the map lets the null-coalescing chain do the work.
     *
     * @return array<string, int>
     */
    private function overrides(): array
    {
        return $this->overrides ??= ServiceTherapist::query()
            ->whereNotNull('break_blocks')
            ->get(['therapist_id', 'service_id', 'break_blocks'])
            ->mapWithKeys(fn (ServiceTherapist $row): array => [
                $row->therapist_id.'|'.$row->service_id => (int) $row->break_blocks,
            ])
            ->all();
    }
}
