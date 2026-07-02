<?php

namespace App\Support\Reservations;

/**
 * Pure, database-free implementation of the reservation slot-offering rules.
 *
 * The full specification (with worked cases) lives in
 * docs/non-technical-specification/reservation-system-logic.md. The goal is that
 * a therapist's day never accrues unfillable gaps between clients.
 *
 * Everything is computed in integer "minutes since midnight" on a single local
 * date — never as timezone-aware instants — because reservation times are stored
 * as wall-clock `H:i:s` strings.
 *
 * Two regimes govern a free segment of a work block:
 *
 *  - **Free rule (G-FREE):** the segment is bounded on the right by the end of the
 *    work block, or by a "terminal" reservation that itself ends at the block end.
 *    Every block-aligned start is offered up to where the service still fits. When
 *    the segment begins at the work-block start, the "leave room before" rule only
 *    offers the very first start, then starts that leave at least one whole public
 *    booking + break before them.
 *
 *  - **Strict gluing (G-STICK):** the segment is bounded on the right by a
 *    non-terminal reservation. A start is offered only from the segment's anchor,
 *    and only when the rest of the gap can be consumed exactly by a chain of
 *    bookings (no time is left stranded between two clients).
 *
 * A 15-minute break follows every booking, except when the booking ends exactly at
 * the work-block end (the therapist gets that break naturally).
 */
class GapFiller
{
    /**
     * @param  array<int, int>  $allDurations  Durations (minutes) usable for chaining/solvability — includes non-public lengths.
     * @param  array<int, int>  $publicDurations  Publicly bookable durations; their smallest footprint sets the work-block-start threshold.
     * @param  int  $breakMinutes  Break after each booking.
     * @param  int  $blockStep  Granularity of offered starts (one reservation block).
     */
    public function __construct(
        protected array $allDurations,
        protected array $publicDurations,
        protected int $breakMinutes,
        protected int $blockStep = 15,
    ) {}

    /**
     * Offered start minutes per surfaced duration within a single work block.
     *
     * @param  int  $blockStart  Work-block start, minutes since midnight.
     * @param  int  $blockEnd  Work-block end, minutes since midnight.
     * @param  array<int, array{0: int, 1: int}>  $busy  Existing reservations within the block as [startMin, endMin]; need not be sorted.
     * @param  array<int, int>  $surfaceDurations  Durations to actually emit offers for.
     * @return array<int, array<int, int>> duration => sorted unique start minutes
     */
    public function offers(int $blockStart, int $blockEnd, array $busy, array $surfaceDurations): array
    {
        $result = array_fill_keys($surfaceDurations, []);

        foreach ($this->segments($blockStart, $blockEnd, $busy) as $segment) {
            if ($segment['end'] <= $segment['start']) {
                continue;
            }

            foreach ($surfaceDurations as $duration) {
                foreach ($this->offersInSegment($segment, $duration, $blockEnd) as $start) {
                    $result[$duration][] = $start;
                }
            }
        }

        foreach ($result as $duration => $starts) {
            $unique = array_values(array_unique($starts));
            sort($unique);
            $result[$duration] = $unique;
        }

        return $result;
    }

    /**
     * Split a work block into free segments, each tagged with its regime.
     *
     * @param  array<int, array{0: int, 1: int}>  $busy
     * @return array<int, array{start: int, end: int, atBlockStart: bool, stick: bool, rightIsBlockEnd: bool}>
     */
    protected function segments(int $blockStart, int $blockEnd, array $busy): array
    {
        usort($busy, fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $segments = [];
        $cursor = $blockStart;
        $atBlockStart = true;

        foreach ($busy as [$start, $end]) {
            // The reservation is the right wall of the segment before it. It is
            // "terminal" when it ends at the block end, in which case the gap
            // before it follows the free rule rather than strict gluing. Either
            // way the right boundary is the reservation's start, so a booking must
            // still leave its break before it (rightIsBlockEnd stays false).
            $terminal = $end >= $blockEnd;

            $segments[] = [
                'start' => $cursor,
                'end' => $start,
                'atBlockStart' => $atBlockStart,
                'stick' => ! $terminal,
                'rightIsBlockEnd' => false,
            ];

            $cursor = $end >= $blockEnd ? $blockEnd : $end + $this->breakMinutes;
            $atBlockStart = false;
        }

        // Trailing segment up to the block end always follows the free rule.
        $segments[] = [
            'start' => $cursor,
            'end' => $blockEnd,
            'atBlockStart' => $atBlockStart,
            'stick' => false,
            'rightIsBlockEnd' => true,
        ];

        return $segments;
    }

    /**
     * @param  array{start: int, end: int, atBlockStart: bool, stick: bool, rightIsBlockEnd: bool}  $segment
     * @return array<int, int>
     */
    protected function offersInSegment(array $segment, int $duration, int $blockEnd): array
    {
        return $segment['stick']
            ? $this->stickOffers($segment, $duration)
            : $this->freeOffers($segment, $duration, $blockEnd);
    }

    /**
     * Strict gluing: only the anchor is offered, and only when the remainder of the
     * gap is exactly fillable by a chain of bookings.
     *
     * @param  array{start: int, end: int}  $segment
     * @return array<int, int>
     */
    protected function stickOffers(array $segment, int $duration): array
    {
        $gap = $segment['end'] - $segment['start'];
        $remainder = $gap - $this->cost($duration);

        if ($remainder >= 0 && $this->isExactlyFillable($remainder)) {
            return [$segment['start']];
        }

        return [];
    }

    /**
     * Free rule: every block-aligned start that still fits, honouring the
     * "leave room before" rule when the segment opens at the work-block start.
     *
     * @param  array{start: int, end: int, atBlockStart: bool, rightIsBlockEnd: bool}  $segment
     * @return array<int, int>
     */
    protected function freeOffers(array $segment, int $duration, int $blockEnd): array
    {
        // Right boundary: a booking ending exactly at the block end needs no break;
        // a booking before a terminal reservation must still leave its break.
        $latest = $segment['rightIsBlockEnd']
            ? $blockEnd - $duration
            : $segment['end'] - $this->cost($duration);

        $offers = [];
        $threshold = $this->blockStartThreshold();

        for ($start = $segment['start']; $start <= $latest; $start += $this->blockStep) {
            $withinThreshold = $start > $segment['start']
                && ($start - $segment['start']) < $threshold;

            if ($segment['atBlockStart'] && $withinThreshold) {
                continue;
            }

            $offers[] = $start;
        }

        return $offers;
    }

    /**
     * The footprint a booking consumes: its duration plus the trailing break.
     */
    protected function cost(int $duration): int
    {
        return $duration + $this->breakMinutes;
    }

    /**
     * Smallest publicly bookable footprint — the minimum space that must remain
     * before the first booking of a work block for another client to fit there.
     */
    protected function blockStartThreshold(): int
    {
        return min(array_map(fn (int $duration): int => $this->cost($duration), $this->publicDurations));
    }

    /**
     * Whether a length can be tiled exactly by a chain of booking footprints
     * (unbounded combination of `cost()` over every chainable duration).
     */
    protected function isExactlyFillable(int $length): bool
    {
        if ($length === 0) {
            return true;
        }

        if ($length < 0) {
            return false;
        }

        $costs = array_map(fn (int $duration): int => $this->cost($duration), $this->allDurations);

        $reachable = array_fill(0, $length + 1, false);
        $reachable[0] = true;

        for ($total = 1; $total <= $length; $total++) {
            foreach ($costs as $cost) {
                if ($total - $cost >= 0 && $reachable[$total - $cost]) {
                    $reachable[$total] = true;
                    break;
                }
            }
        }

        return $reachable[$length];
    }
}
