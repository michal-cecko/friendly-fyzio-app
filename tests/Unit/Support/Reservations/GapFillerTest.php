<?php

namespace Tests\Unit\Support\Reservations;

use App\Support\Reservations\GapFiller;
use PHPUnit\Framework\TestCase;

/**
 * Golden tests for the slot-offering algorithm, driven by the worked cases in
 * docs/non-technical-specification/reservation-system-logic.md.
 *
 * Therapist Denisa, work block 08:00–16:00, 15-minute break after every booking
 * (waived only when a booking ends exactly at 16:00). Public durations are 60 and
 * 90 minutes; 30 minutes is non-public but still chains.
 *
 * NOTE on cases 4 and 7: the spec's printed tables place the post-reservation
 * anchor at a 90-minute booking's *end* (e.g. 9:15 + 90 = 10:45) without the
 * trailing break, which contradicts the document's own rule ("a 15-minute break
 * follows every massage") and cases 2/3/5/6, where the break is always added.
 * We follow the explicit break rule — a 90-minute reservation that does not end at
 * the shift end is followed by a break too — so those two expectations differ from
 * the doc's tables by that 15-minute break. This is the real-world correct
 * behaviour (a therapist needs a break after a 90-minute session as well).
 */
class GapFillerTest extends TestCase
{
    private const BLOCK_START = 480;  // 08:00

    private const BLOCK_END = 960;    // 16:00

    private function filler(): GapFiller
    {
        return new GapFiller(
            allDurations: [30, 60, 90],
            publicDurations: [60, 90],
            breakMinutes: 15,
        );
    }

    /**
     * Offers for all three durations within Denisa's full work block.
     *
     * @param  array<int, array{0: int, 1: int}>  $busy
     * @return array<int, array<int, int>>
     */
    private function offers(array $busy): array
    {
        return $this->filler()->offers(self::BLOCK_START, self::BLOCK_END, $busy, [30, 60, 90]);
    }

    /**
     * Inclusive list of block-aligned minutes from $from to $to.
     *
     * @return array<int, int>
     */
    private function every15(int $from, int $to): array
    {
        $values = [];
        for ($minute = $from; $minute <= $to; $minute += 15) {
            $values[] = $minute;
        }

        return $values;
    }

    public function test_case_1_empty_day(): void
    {
        $offers = $this->offers([]);

        // "08:00 or 09:15" then every 15 min up to where the booking ends by 16:00.
        $this->assertSame([480, ...$this->every15(555, 930)], $offers[30]);
        $this->assertSame([480, ...$this->every15(555, 900)], $offers[60]);
        $this->assertSame([480, ...$this->every15(555, 870)], $offers[90]);
    }

    public function test_case_2_existing_60_minute_reservation_at_0915(): void
    {
        // 09:15–10:15, anchor 10:30.
        $offers = $this->offers([[555, 615]]);

        $this->assertSame([480, ...$this->every15(630, 900)], $offers[60]);
        $this->assertSame($this->every15(630, 930), $offers[30]);
        $this->assertSame($this->every15(630, 870), $offers[90]);
    }

    public function test_case_3_existing_30_minute_reservation_at_0915(): void
    {
        // 09:15–09:45, anchor 10:00.
        $offers = $this->offers([[555, 585]]);

        $this->assertSame([480, ...$this->every15(600, 900)], $offers[60]);
        $this->assertSame($this->every15(600, 930), $offers[30]);
        $this->assertSame($this->every15(600, 870), $offers[90]);
    }

    public function test_case_4_existing_90_minute_reservation_at_0915(): void
    {
        // 09:15–10:45; with the mandatory break the anchor is 11:00 (not the doc's
        // 10:45 — see the class note).
        $offers = $this->offers([[555, 645]]);

        $this->assertSame([480, ...$this->every15(660, 900)], $offers[60]);
        $this->assertSame($this->every15(660, 930), $offers[30]);
        $this->assertSame($this->every15(660, 870), $offers[90]);
    }

    public function test_case_5_existing_60_minute_reservation_at_1030(): void
    {
        // 10:30–11:30, anchor 11:45. The 150-minute gap before it is exactly
        // fillable, so 30/60/90 are all offered at 08:00.
        $offers = $this->offers([[630, 690]]);

        $this->assertSame([480, ...$this->every15(705, 930)], $offers[30]);
        $this->assertSame([480, ...$this->every15(705, 900)], $offers[60]);
        $this->assertSame([480, ...$this->every15(705, 870)], $offers[90]);
    }

    public function test_case_6_existing_60_minute_reservation_at_1100(): void
    {
        // 11:00–12:00, anchor 12:15. The 180-minute gap is exactly fillable from
        // 08:00 for every duration (30 via 3×30, 60 via 60+90, 90 via 90+60).
        $offers = $this->offers([[660, 720]]);

        $this->assertSame([480, ...$this->every15(735, 930)], $offers[30]);
        $this->assertSame([480, ...$this->every15(735, 900)], $offers[60]);
        $this->assertSame([480, ...$this->every15(735, 870)], $offers[90]);
    }

    public function test_case_7_two_reservations(): void
    {
        // 09:15–10:15 (anchor 10:30) and 13:00–14:30 (anchor 14:45 with the break —
        // the doc's table uses 14:30; see the class note). Gap 10:30–13:00 (150 min)
        // is exactly fillable so all durations are offered at 10:30.
        $offers = $this->offers([[555, 615], [780, 870]]);

        $this->assertSame([480, 630, 885, 900], $offers[60]);
        $this->assertSame([630, 885, 900, 915, 930], $offers[30]);
        $this->assertSame([630], $offers[90]);
    }

    public function test_case_8_terminal_reservation_at_1500(): void
    {
        // 15:00–16:00 ends at the shift end, so the gap before it follows the free
        // rule but must leave a break before 15:00.
        $offers = $this->offers([[900, 960]]);

        $this->assertSame([480, ...$this->every15(555, 855)], $offers[30]);
        $this->assertSame([480, ...$this->every15(555, 825)], $offers[60]);
        $this->assertSame([480, ...$this->every15(555, 795)], $offers[90]);
    }

    public function test_public_projection_never_surfaces_non_public_durations(): void
    {
        // The wizard only ever asks for a single public duration; 30-minute starts
        // must never be surfaced even though they enable chains internally.
        $offers = $this->filler()->offers(self::BLOCK_START, self::BLOCK_END, [[660, 720]], [60, 90]);

        $this->assertArrayNotHasKey(30, $offers);
        $this->assertSame([480, ...$this->every15(735, 900)], $offers[60]);
        $this->assertSame([480, ...$this->every15(735, 870)], $offers[90]);
    }

    public function test_lunch_gap_between_two_work_blocks_stays_unfillable(): void
    {
        // Morning 08:00–12:00 and afternoon 13:00–16:00 are independent blocks; the
        // 12:00–13:00 lunch gap is never offered because each block is filled on its
        // own. Here we assert the morning block alone caps bookings at 12:00.
        $morning = $this->filler()->offers(480, 720, [], [60, 90]);

        $this->assertSame(660, end($morning[60]));   // last 60-min start 11:00 → ends 12:00
        $this->assertSame(630, end($morning[90]));   // last 90-min start 10:30 → ends 12:00
    }
}
