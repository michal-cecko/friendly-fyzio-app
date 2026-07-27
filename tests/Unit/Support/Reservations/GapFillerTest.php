<?php

namespace Tests\Unit\Support\Reservations;

use App\Support\Reservations\GapFiller;
use PHPUnit\Framework\TestCase;

/**
 * Golden tests for the slot-offering algorithm, driven by the worked cases in
 * docs/non-technical-specification/reservation-system-logic.md and the rules the
 * client settled on 27 March 2026.
 *
 * Therapist Denisa, work block 08:00–16:00, 15-minute break after every booking
 * (waived only when a booking ends exactly at 16:00). She sells 60- and 90-minute
 * massages online; the 30-minute one is internal, so it neither anchors nor chains.
 *
 * Every offered start is an exact sum of bookable footprints (75 = 60+15, 105 =
 * 90+15) away from the segment's anchor. That is the client's rule — a free shift
 * of 08:00–12:15 offers 08:00, 09:15, 09:45, 10:30 and 11:00, and specifically not
 * 10:00, so no unsellable gap can open in front of a booking.
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

    /**
     * Denisa's public catalogue: 60 and 90 minutes, both anchoring and chaining.
     */
    private function filler(): GapFiller
    {
        return GapFiller::uniform(
            allDurations: [60, 90],
            anchorDurations: [60, 90],
            breakMinutes: 15,
        );
    }

    /**
     * Offers for both public durations within Denisa's full work block. Busy
     * intervals are given as [start, end] and take Denisa's own 15-minute break.
     *
     * @param  array<int, array{0: int, 1: int}>  $busy
     * @return array<int, array<int, int>>
     */
    private function offers(array $busy): array
    {
        $withBreaks = array_map(fn (array $interval): array => [...$interval, 15], $busy);

        return $this->filler()->offers(self::BLOCK_START, self::BLOCK_END, $withBreaks, [60, 90]);
    }

    public function test_the_clients_worked_example_offers_only_clean_anchors(): void
    {
        // The 08:00–12:15 shift the client checked by hand on 27 March.
        $offers = $this->filler()->offers(480, 735, [], [60]);

        $this->assertSame([480, 555, 585, 630, 660], $offers[60]);

        // 10:00 is 120 minutes from the shift start and 120 is no sum of 75 and
        // 105, so booking it would strand time the therapist cannot sell.
        $this->assertNotContains(600, $offers[60]);
        $this->assertNotContains(525, $offers[60]);
    }

    public function test_case_1_empty_day(): void
    {
        $offers = $this->offers([]);

        // 08:00, 09:15, 09:45, 10:30, 11:00, 11:30, 11:45, 12:15, 12:45 … The lattice
        // turns dense from 360 minutes on; 345 is the last unreachable sum of 75 and
        // 105, which is why 13:45 is missing while 13:30 and 14:00 are there.
        $this->assertSame(
            [480, 555, 585, 630, 660, 690, 705, 735, 765, 780, 795, 810, 840, 855, 870, 885, 900],
            $offers[60],
        );
        $this->assertSame(
            [480, 555, 585, 630, 660, 690, 705, 735, 765, 780, 795, 810, 840, 855, 870],
            $offers[90],
        );
    }

    public function test_case_2_existing_60_minute_reservation_at_0915(): void
    {
        // 09:15–10:15, anchor 10:30. The 75-minute gap before it takes exactly one
        // 60-minute booking, so 08:00 is offered.
        $offers = $this->offers([[555, 615]]);

        $this->assertSame([480, 630, 705, 735, 780, 810, 840, 855, 885], $offers[60]);
        $this->assertSame([630, 705, 735, 780, 810, 840, 855], $offers[90]);
    }

    public function test_case_3_existing_30_minute_reservation_at_0915(): void
    {
        // An internal 30-minute booking Denisa entered herself: 09:15–09:45,
        // anchor 10:00. The anchor moves even though the public never saw it.
        $offers = $this->offers([[555, 585]]);

        $this->assertSame([480, 600, 675, 705, 750, 780, 810, 825, 855, 885, 900], $offers[60]);
        $this->assertSame([600, 675, 705, 750, 780, 810, 825, 855], $offers[90]);
    }

    public function test_case_4_existing_90_minute_reservation_at_0915(): void
    {
        // 09:15–10:45; with the mandatory break the anchor is 11:00 (not the doc's
        // 10:45 — see the class note).
        $offers = $this->offers([[555, 645]]);

        $this->assertSame([480, 660, 735, 765, 810, 840, 870, 885], $offers[60]);
        $this->assertSame([660, 735, 765, 810, 840, 870], $offers[90]);
    }

    public function test_case_5_offers_every_anchor_in_the_gap(): void
    {
        // 10:30–11:30, anchor 11:45. The 150-minute gap before it takes 60+60, so
        // both 08:00 and 09:15 are offered — the client asked for both explicitly.
        $offers = $this->offers([[630, 690]]);

        $this->assertSame([480, 555, 705, 780, 810, 855, 885], $offers[60]);

        // 90 minutes would leave 45 stranded, which only the internal 30-minute
        // service could absorb — and that one no longer chains.
        $this->assertSame([705, 780, 810, 855], $offers[90]);
    }

    public function test_case_6_offers_the_later_link_of_each_chain(): void
    {
        // 11:00–12:00, anchor 12:15. The 180-minute gap is 60+90 or 90+60, so a
        // 60-minute booking fits at 08:00 and 09:45, a 90-minute one at 08:00 and
        // 09:15 — exactly what the client asked for on 27 March.
        $offers = $this->offers([[660, 720]]);

        $this->assertSame([480, 585, 735, 810, 840, 885], $offers[60]);
        $this->assertSame([480, 555, 735, 810, 840], $offers[90]);
    }

    public function test_case_7_two_reservations(): void
    {
        // 09:15–10:15 (anchor 10:30) and 13:00–14:30 (anchor 14:45 with the break —
        // the doc's table uses 14:30; see the class note). The 150-minute middle gap
        // offers 60 minutes at both 10:30 and 11:45.
        $offers = $this->offers([[555, 615], [780, 870]]);

        $this->assertSame([480, 630, 705, 885], $offers[60]);

        // Nowhere left for 90 minutes: every gap either is too short or would
        // strand 45 minutes, and 14:45 + 105 runs past the end of the shift.
        $this->assertSame([], $offers[90]);
    }

    public function test_case_8_terminal_reservation_at_1500(): void
    {
        // 15:00–16:00 ends at the shift end, so the gap before it follows the free
        // rule but must leave a break before 15:00.
        $offers = $this->offers([[900, 960]]);

        $this->assertSame([480, 555, 585, 630, 660, 690, 705, 735, 765, 780, 795, 810], $offers[60]);
        $this->assertSame([480, 555, 585, 630, 660, 690, 705, 735, 765, 780, 795], $offers[90]);
    }

    public function test_anchors_come_only_from_lengths_a_client_can_book(): void
    {
        // Denisa's 30-minute massage is internal. It must never move the lattice:
        // 08:45 (30+15 from the shift start) is not a legal start for anybody.
        $filler = GapFiller::uniform(
            allDurations: [30, 60, 90],
            anchorDurations: [60, 90],
            breakMinutes: 15,
        );

        $offers = $filler->offers(self::BLOCK_START, self::BLOCK_END, [], [60, 90]);

        $this->assertArrayNotHasKey(30, $offers);
        $this->assertNotContains(525, $offers[60]);
        $this->assertSame($this->offers([])[60], $offers[60]);
    }

    public function test_a_length_nobody_can_book_online_still_fills_a_remainder(): void
    {
        // Same 150-minute gap as case 5. A 90-minute booking at 08:00 leaves 45
        // minutes, which Denisa can still fill with her internal 30-minute massage —
        // so once that length chains, the offer comes back.
        $filler = new GapFiller(
            allCosts: [30 => 45, 60 => 75, 90 => 105],
            anchorCosts: [60 => 75, 90 => 105],
        );

        $offers = $filler->offers(self::BLOCK_START, self::BLOCK_END, [[630, 690, 15]], [90]);

        $this->assertContains(480, $offers[90]);
    }

    public function test_break_travels_with_the_booking_that_earned_it(): void
    {
        // The same 09:15–10:15 booking as case 2, but this one was a massage its
        // therapist rests 30 minutes after. The anchor moves to 10:45, even though
        // what is being offered afterwards only costs a 15-minute break itself.
        $offers = $this->filler()->offers(self::BLOCK_START, self::BLOCK_END, [[555, 615, 30]], [60, 90]);

        $this->assertSame([480, 645, 720, 750, 795, 825, 855, 870, 900], $offers[60]);
        $this->assertSame([645, 720, 750, 795, 825, 855, 870], $offers[90]);
    }

    public function test_a_therapist_who_never_rests_offers_back_to_back_slots(): void
    {
        // Break 0: the anchor is the booking's own end, so 10:15 follows 09:15–10:15
        // with nothing in between, and the lattice steps by 60/90 rather than 75/105.
        // The 08:00 opening is gone — without breaks the 75-minute gap before the
        // booking is no longer tileable.
        $filler = GapFiller::uniform(
            allDurations: [60, 90],
            anchorDurations: [60, 90],
            breakMinutes: 0,
        );

        $offers = $filler->offers(self::BLOCK_START, self::BLOCK_END, [[555, 615, 0]], [60]);

        $this->assertSame([615, 675, 705, 735, 765, 795, 825, 855, 885], $offers[60]);
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
