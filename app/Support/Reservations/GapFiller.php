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
 * Every offered start sits on the segment's **anchor lattice**: the segment's own
 * anchor (the work-block start, or the previous booking's end plus its break) plus
 * any sum of offerable booking footprints. Nothing else is ever offered, so the
 * space in front of a booking can always be sold whole to other clients — a shift
 * of 08:00–12:15 with 60- and 90-minute services offers 08:00, 09:15, 09:45, 10:30
 * and 11:00, but never 10:00, because 120 minutes is no sum of 75 and 105.
 *
 * On top of the lattice, two regimes govern how far right a segment reaches:
 *
 *  - **Free rule (G-FREE):** the segment is bounded on the right by the end of the
 *    work block, or by a "terminal" reservation that itself ends at the block end.
 *    Every lattice start is offered up to where the service still fits; whatever is
 *    left over at the shift end is the therapist's own time.
 *
 *  - **Strict gluing (G-STICK):** the segment is bounded on the right by a
 *    non-terminal reservation. A lattice start is offered only when the rest of the
 *    gap behind it can be consumed exactly by a chain of bookings, so no time is
 *    left stranded between two clients. Several starts in one gap can qualify.
 *
 * A break follows every booking, except when the booking ends exactly at the
 * work-block end (the therapist gets that break naturally). The break is not one
 * number for the whole day: it belongs to the therapist performing the visit and
 * may differ per service, so it travels with each booking — as the third element
 * of a busy interval for work that already exists, and folded into the "cost" of
 * a duration for work that might still be booked.
 */
class GapFiller
{
    /**
     * Lattices already computed, keyed by span — one segment is walked once per
     * surfaced duration, and they all share the same set of anchors.
     *
     * @var array<int, array<int, int>>
     */
    protected array $latticeCache = [];

    /**
     * @param  array<int, int>  $allCosts  duration => footprint (duration + break) for every chainable length, including the ones nobody can book online.
     * @param  array<int, int>  $anchorCosts  duration => footprint for the lengths a client can actually book; their sums generate the lattice of offered starts.
     */
    public function __construct(
        protected array $allCosts,
        protected array $anchorCosts,
    ) {}

    /**
     * Build a filler for a therapist who takes the same break after everything —
     * the common case, and the shape the spec's worked examples are written in.
     *
     * @param  array<int, int>  $allDurations
     * @param  array<int, int>  $anchorDurations
     */
    public static function uniform(
        array $allDurations,
        array $anchorDurations,
        int $breakMinutes,
    ): self {
        $cost = fn (array $durations): array => array_combine(
            $durations,
            array_map(fn (int $duration): int => $duration + $breakMinutes, $durations),
        );

        return new self($cost($allDurations), $cost($anchorDurations));
    }

    /**
     * Offered start minutes per surfaced duration within a single work block.
     *
     * @param  int  $blockStart  Work-block start, minutes since midnight.
     * @param  int  $blockEnd  Work-block end, minutes since midnight.
     * @param  array<int, array{0: int, 1: int, 2: int}>  $busy  Existing bookings within the block as [startMin, endMin, breakAfterMin]; need not be sorted.
     * @param  array<int, int>  $surfaceDurations  Durations to actually emit offers for; each must appear in $allCosts.
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
     * @param  array<int, array{0: int, 1: int, 2: int}>  $busy
     * @return array<int, array{start: int, end: int, stick: bool, rightIsBlockEnd: bool}>
     */
    protected function segments(int $blockStart, int $blockEnd, array $busy): array
    {
        usort($busy, fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $segments = [];
        $cursor = $blockStart;

        foreach ($busy as [$start, $end, $breakAfter]) {
            // The reservation is the right wall of the segment before it. It is
            // "terminal" when it ends at the block end, in which case the gap
            // before it follows the free rule rather than strict gluing. Either
            // way the right boundary is the reservation's start, so a booking must
            // still leave its break before it (rightIsBlockEnd stays false).
            $terminal = $end >= $blockEnd;

            $segments[] = [
                'start' => $cursor,
                'end' => $start,
                'stick' => ! $terminal,
                'rightIsBlockEnd' => false,
            ];

            // The break belongs to the booking that just ended, not to whatever
            // is offered next — a therapist who rests 30 minutes after a massage
            // rests 30 minutes whoever books the slot after it.
            $cursor = $end >= $blockEnd ? $blockEnd : $end + $breakAfter;
        }

        // Trailing segment up to the block end always follows the free rule.
        $segments[] = [
            'start' => $cursor,
            'end' => $blockEnd,
            'stick' => false,
            'rightIsBlockEnd' => true,
        ];

        return $segments;
    }

    /**
     * @param  array{start: int, end: int, stick: bool, rightIsBlockEnd: bool}  $segment
     * @return array<int, int>
     */
    protected function offersInSegment(array $segment, int $duration, int $blockEnd): array
    {
        return $segment['stick']
            ? $this->stickOffers($segment, $duration)
            : $this->freeOffers($segment, $duration, $blockEnd);
    }

    /**
     * Strict gluing: every lattice start whose remainder of the gap is exactly
     * fillable by a chain of bookings. More than one can qualify — a 150-minute
     * gap takes a 60-minute booking at its anchor and again 75 minutes later.
     *
     * @param  array{start: int, end: int}  $segment
     * @return array<int, int>
     */
    protected function stickOffers(array $segment, int $duration): array
    {
        $gap = $segment['end'] - $segment['start'];
        $cost = $this->cost($duration);

        $offers = [];

        foreach ($this->anchorOffsets($gap) as $offset) {
            $remainder = $gap - $offset - $cost;

            if ($remainder >= 0 && $this->isExactlyFillable($remainder)) {
                $offers[] = $segment['start'] + $offset;
            }
        }

        return $offers;
    }

    /**
     * Free rule: every lattice start that still fits before the right wall.
     *
     * @param  array{start: int, end: int, rightIsBlockEnd: bool}  $segment
     * @return array<int, int>
     */
    protected function freeOffers(array $segment, int $duration, int $blockEnd): array
    {
        // Right boundary: a booking ending exactly at the block end needs no break;
        // a booking before a terminal reservation must still leave its break.
        $latest = $segment['rightIsBlockEnd']
            ? $blockEnd - $duration
            : $segment['end'] - $this->cost($duration);

        return array_map(
            fn (int $offset): int => $segment['start'] + $offset,
            $this->anchorOffsets($latest - $segment['start']),
        );
    }

    /**
     * Offsets from a segment's anchor that are an exact sum of bookable footprints
     * — the only starts a client may pick, so that whatever is left in front of
     * their booking can still be sold whole. Always contains 0 (the anchor itself).
     *
     * @return array<int, int> ascending, capped at $span
     */
    protected function anchorOffsets(int $span): array
    {
        if ($span < 0) {
            return [];
        }

        if (isset($this->latticeCache[$span])) {
            return $this->latticeCache[$span];
        }

        $reachable = array_fill(0, $span + 1, false);
        $reachable[0] = true;
        $offsets = [];

        for ($offset = 0; $offset <= $span; $offset++) {
            if (! $reachable[$offset]) {
                continue;
            }

            $offsets[] = $offset;

            foreach ($this->anchorCosts as $cost) {
                if ($cost > 0 && $offset + $cost <= $span) {
                    $reachable[$offset + $cost] = true;
                }
            }
        }

        return $this->latticeCache[$span] = $offsets;
    }

    /**
     * The footprint a booking consumes: its duration plus the trailing break of
     * the therapist the filler was built for. A duration nobody was costed for
     * falls back to no break at all, which only happens when a caller surfaces a
     * length it never declared.
     */
    protected function cost(int $duration): int
    {
        return $this->allCosts[$duration] ?? $duration;
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

        $costs = array_values($this->allCosts);

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
