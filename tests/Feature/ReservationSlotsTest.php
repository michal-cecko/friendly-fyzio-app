<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Enums\ServiceType;
use App\Enums\ServiceVisibility;
use App\Models\Lesson;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomBlocking;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\StaffProfile;
use App\Models\TherapistWorkBlock;
use App\Support\Reservations\ReservationSlots;
use App\Support\Reservations\Slot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Unit\Support\Reservations\GapFillerTest;

class ReservationSlotsTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $date;

    private Room $room;

    private Service $service;

    private Service $longService;

    private StaffProfile $therapist;

    protected function setUp(): void
    {
        parent::setUp();

        // A Monday safely in the future so the lead-time guard never drops slots.
        $this->date = Carbon::today()->addWeeks(8)->startOfWeek(Carbon::MONDAY);
        $this->room = Room::factory()->create();

        $category = ServiceCategory::factory()->create(['type' => ServiceType::Massage]);

        // Public 60-minute service under test plus a 90-minute sibling: together
        // they generate the anchor lattice (75 and 105 minutes with the break). The
        // 30-minute one is the internal massage staff book by hand — hidden, so it
        // neither anchors nor chains.
        $this->service = Service::factory()->create([
            'category_id' => $category->id,
            'duration_minutes' => 60,
            'visibility' => ServiceVisibility::Public,
            'published_at' => now(),
        ]);
        Service::factory()->create(['category_id' => $category->id, 'duration_minutes' => 30, 'visibility' => ServiceVisibility::Hidden, 'published_at' => now()]);
        $this->longService = Service::factory()->create(['category_id' => $category->id, 'duration_minutes' => 90, 'visibility' => ServiceVisibility::Public, 'published_at' => now()]);

        // One block of break after everything, taken from the therapist's own
        // default — the spec's 15 minutes, and what every expectation below assumes.
        $this->therapist = StaffProfile::factory()->create(['published_at' => now(), 'break_blocks' => 1]);
        $this->service->therapists()->attach($this->therapist);
        $this->longService->therapists()->attach($this->therapist);

        $this->schedule($this->therapist, $this->room, '08:00', '16:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function schedule(StaffProfile $therapist, Room $room, string $start, string $end, ?Carbon $date = null): void
    {
        TherapistWorkBlock::factory()->create([
            'therapist_id' => $therapist->id,
            'room_id' => $room->id,
            'work_date' => ($date ?? $this->date)->toDateString(),
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }

    /** @return array<int, int> */
    private function startMinutes(array $slots): array
    {
        return array_map(fn (Slot $slot): int => $slot->startMin, $slots);
    }

    /**
     * The starts the engine should offer from an anchor: every exact sum of the
     * given booking footprints, up to the last minute the service still fits.
     * Denisa's default footprints are 75 (60+15) and 105 (90+15).
     *
     * The precise lists per spec case are pinned in
     * {@see GapFillerTest}; here the shape only has
     * to follow whatever the database picture works out to.
     *
     * @param  array<int, int>  $footprints
     * @return array<int, int>
     */
    private function anchored(int $from, int $latest, array $footprints = [75, 105]): array
    {
        $starts = [$from];

        for ($i = 0; $i < count($starts); $i++) {
            foreach ($footprints as $footprint) {
                $next = $starts[$i] + $footprint;

                if ($next <= $latest && ! in_array($next, $starts, true)) {
                    $starts[] = $next;
                }
            }
        }

        sort($starts);

        return $starts;
    }

    private function slots(): ReservationSlots
    {
        return app(ReservationSlots::class);
    }

    public function test_available_times_on_an_empty_day_match_the_gap_fill_rules(): void
    {
        $slots = $this->slots()->availableTimes($this->service, $this->date);

        // 60-minute empty-day offering: 08:00, then 09:15…15:00.
        $this->assertSame($this->anchored(480, 900), $this->startMinutes($slots));

        $this->assertSame($this->therapist->id, $slots[0]->therapistId);
        $this->assertSame($this->room->id, $slots[0]->roomId);
        $this->assertSame('08:00', $slots[0]->start());
        $this->assertSame('09:00', $slots[0]->end());
    }

    public function test_existing_reservation_reshapes_offered_times(): void
    {
        // A 60-minute reservation at 09:15 (anchor 10:30): only 08:00 survives in
        // the first gap, then the free tail from 10:30.
        Reservation::factory()->create([
            'service_id' => $this->service->id,
            'therapist_id' => $this->therapist->id,
            'room_id' => $this->room->id,
            'reservation_date' => $this->date->toDateString(),
            'start_time' => '09:15',
            'end_time' => '10:15',
            'status' => ReservationStatus::Confirmed,
        ]);

        $slots = $this->slots()->availableTimes($this->service, $this->date);

        $this->assertSame([480, ...$this->anchored(630, 900)], $this->startMinutes($slots));
    }

    public function test_the_therapists_own_default_break_shapes_the_offering(): void
    {
        // Two blocks instead of one: every footprint grows by 15 minutes, so the
        // whole lattice is redrawn on 90 (60+30) and 120 (90+30) instead of 75/105
        // — the first start after 08:00 becomes 09:30 rather than 09:15.
        $this->therapist->update(['break_blocks' => 2]);

        $slots = $this->slots()->availableTimes($this->service, $this->date);

        $this->assertSame($this->anchored(480, 900, [90, 120]), $this->startMinutes($slots));
    }

    public function test_a_per_service_override_beats_the_therapists_default(): void
    {
        // The therapist rests one block after everything except this service, which
        // earns two. Only the 60-minute footprint grows, so the lattice is drawn on
        // 90 (60+30) and the 90-minute sibling's unchanged 105.
        $this->service->therapists()->updateExistingPivot($this->therapist->id, ['break_blocks' => 2]);

        $slots = $this->slots()->availableTimes($this->service, $this->date);

        $this->assertSame($this->anchored(480, 900, [90, 105]), $this->startMinutes($slots));
    }

    public function test_a_clients_only_service_still_generates_anchors(): void
    {
        // "Pro klienty" services (the physio "kontrolní" ones) are bookable in the
        // wizard, so the space they would occupy is sellable and they must move the
        // lattice. A 45-minute one adds a 60-minute footprint: 09:00 becomes legal.
        Service::factory()->create([
            'category_id' => $this->service->category_id,
            'duration_minutes' => 45,
            'visibility' => ServiceVisibility::Clients,
            'published_at' => now(),
        ])->therapists()->attach($this->therapist);

        $starts = $this->startMinutes($this->slots()->availableTimes($this->service, $this->date));

        $this->assertSame($this->anchored(480, 900, [60, 75, 105]), $starts);
        $this->assertContains(540, $starts);
    }

    public function test_a_hidden_service_never_moves_the_lattice(): void
    {
        // The internal 30-minute massage from the fixture would put 08:45 on the
        // lattice if it counted. Staff can still book it by hand; the public offering
        // must not assume anybody will.
        $starts = $this->startMinutes($this->slots()->availableTimes($this->service, $this->date));

        $this->assertNotContains(525, $starts);
    }

    public function test_a_length_only_a_colleague_performs_does_not_move_the_lattice(): void
    {
        // The 90-minute sibling exists, but this therapist does not perform it, so
        // her day is anchored on 75 alone — 09:45 belongs to her colleague's grid.
        $this->longService->therapists()->detach($this->therapist);

        $starts = $this->startMinutes($this->slots()->availableTimes($this->service, $this->date));

        $this->assertSame($this->anchored(480, 900, [75]), $starts);
        $this->assertNotContains(585, $starts);
    }

    public function test_two_therapists_with_different_breaks_offer_different_times(): void
    {
        // Denisa rests one block, her colleague two. Browsing "kdokoliv" unions
        // both offerings, and the slower colleague's first afternoon-shaped start
        // is her own — the two are costed separately, not with one shared break.
        $colleague = StaffProfile::factory()->create(['published_at' => now(), 'break_blocks' => 2]);
        $this->service->therapists()->attach($colleague);
        $this->longService->therapists()->attach($colleague);
        $this->schedule($colleague, Room::factory()->create(), '08:00', '16:00');

        $slots = $this->slots()->availableTimes($this->service, $this->date);

        $mine = $this->startMinutes(array_values(array_filter(
            $slots,
            fn (Slot $slot): bool => $slot->therapistId === $this->therapist->id,
        )));
        $theirs = $this->startMinutes(array_values(array_filter(
            $slots,
            fn (Slot $slot): bool => $slot->therapistId === $colleague->id,
        )));

        $this->assertSame($this->anchored(480, 900), $mine);
        $this->assertSame($this->anchored(480, 900, [90, 120]), $theirs);
    }

    public function test_an_existing_booking_keeps_the_break_it_was_made_with(): void
    {
        // Booked while the therapist rested two blocks; the snapshot on the row
        // holds the 10:45 anchor even after they drop back to one block, so an
        // existing day is never reshaped underneath its clients.
        $this->therapist->update(['break_blocks' => 2]);

        Reservation::factory()->create([
            'service_id' => $this->service->id,
            'therapist_id' => $this->therapist->id,
            'room_id' => $this->room->id,
            'reservation_date' => $this->date->toDateString(),
            'start_time' => '09:15',
            'end_time' => '10:15',
            'status' => ReservationStatus::Confirmed,
        ]);

        $this->therapist->update(['break_blocks' => 1]);

        $slots = $this->slots()->availableTimes($this->service, $this->date);

        $this->assertSame([480, ...$this->anchored(645, 900)], $this->startMinutes($slots));
    }

    public function test_room_occupied_by_another_therapist_blocks_the_slot(): void
    {
        // A different therapist (who doesn't offer our service) has a booking in the
        // SAME room 09:15–10:15 — the room is occupied, so our therapist's slots must
        // reshape exactly as if the room were busy then.
        $other = StaffProfile::factory()->create(['published_at' => now()]);
        Reservation::factory()->create([
            'therapist_id' => $other->id,
            'room_id' => $this->room->id,
            'reservation_date' => $this->date->toDateString(),
            'start_time' => '09:15',
            'end_time' => '10:15',
            'status' => ReservationStatus::Confirmed,
        ]);

        $slots = $this->slots()->availableTimes($this->service, $this->date);

        $this->assertSame([480, ...$this->anchored(630, 900)], $this->startMinutes($slots));
    }

    public function test_cancelled_reservations_do_not_block_slots(): void
    {
        Reservation::factory()->create([
            'service_id' => $this->service->id,
            'therapist_id' => $this->therapist->id,
            'room_id' => $this->room->id,
            'reservation_date' => $this->date->toDateString(),
            'start_time' => '09:15',
            'end_time' => '10:15',
            'status' => ReservationStatus::Cancelled,
        ]);

        $slots = $this->slots()->availableTimes($this->service, $this->date);

        $this->assertSame($this->anchored(480, 900), $this->startMinutes($slots));
    }

    public function test_available_days_lists_only_days_that_have_slots(): void
    {
        $days = $this->slots()->availableDays($this->service, $this->date->copy(), $this->date->copy()->addDays(6));

        // Only the scheduled Monday has working time in the Mon–Sun window.
        $this->assertSame([$this->date->toDateString()], $days);
    }

    public function test_fully_booked_future_day_is_full_not_available(): void
    {
        // One reservation spanning the whole 08:00–16:00 block leaves no gaps.
        Reservation::factory()->create([
            'service_id' => $this->service->id,
            'therapist_id' => $this->therapist->id,
            'room_id' => $this->room->id,
            'reservation_date' => $this->date->toDateString(),
            'start_time' => '08:00',
            'end_time' => '16:00',
            'status' => ReservationStatus::Confirmed,
        ]);

        $availability = $this->slots()->dayAvailability($this->service, $this->date->copy(), $this->date->copy()->addDays(6));

        $this->assertContains($this->date->toDateString(), $availability['full']);
        $this->assertNotContains($this->date->toDateString(), $availability['available']);
    }

    public function test_day_without_a_schedule_is_neither_available_nor_full(): void
    {
        // The therapist only works the Monday; the Tuesday has no working time at all.
        $tuesday = $this->date->copy()->addDay()->toDateString();

        $availability = $this->slots()->dayAvailability($this->service, $this->date->copy(), $this->date->copy()->addDays(6));

        $this->assertNotContains($tuesday, $availability['available']);
        $this->assertNotContains($tuesday, $availability['full']);
    }

    public function test_today_is_available_when_a_later_slot_is_free(): void
    {
        // "Now" is the scheduled Monday, before opening — its 08:00+ slots are still free.
        Carbon::setTestNow($this->date->copy()->startOfDay());

        $days = $this->slots()->availableDays($this->service, Carbon::today(), Carbon::today()->copy()->addDays(6));

        $this->assertContains($this->date->toDateString(), $days);
    }

    public function test_today_is_never_full_even_when_booked_out(): void
    {
        Carbon::setTestNow($this->date->copy()->startOfDay());

        Reservation::factory()->create([
            'service_id' => $this->service->id,
            'therapist_id' => $this->therapist->id,
            'room_id' => $this->room->id,
            'reservation_date' => $this->date->toDateString(),
            'start_time' => '08:00',
            'end_time' => '16:00',
            'status' => ReservationStatus::Confirmed,
        ]);

        $availability = $this->slots()->dayAvailability($this->service, Carbon::today(), Carbon::today()->copy()->addDays(6));

        // A booked-out today is "late", not a waitlist candidate.
        $this->assertNotContains($this->date->toDateString(), $availability['full']);
        $this->assertNotContains($this->date->toDateString(), $availability['available']);
    }

    public function test_deleting_work_blocks_removes_the_day(): void
    {
        // Vacation workflow: the therapist's work blocks for the day are deleted.
        TherapistWorkBlock::query()
            ->where('therapist_id', $this->therapist->id)
            ->whereDate('work_date', $this->date)
            ->delete();

        $this->assertSame([], $this->slots()->availableTimes($this->service, $this->date));
        $this->assertSame([], $this->slots()->availableDays($this->service, $this->date->copy(), $this->date->copy()->addDays(6)));
    }

    public function test_room_blocking_subtracts_time(): void
    {
        // Block the room 08:00–12:00; only the afternoon sub-block remains.
        RoomBlocking::factory()->create([
            'room_id' => $this->room->id,
            'is_recurring' => false,
            'start_at' => $this->date->copy()->setTime(8, 0),
            'end_at' => $this->date->copy()->setTime(12, 0),
        ]);

        $slots = $this->slots()->availableTimes($this->service, $this->date);

        // Afternoon block 12:00–16:00 behaves like an empty block of its own.
        $this->assertSame($this->anchored(720, 900), $this->startMinutes($slots));
    }

    public function test_resolve_slot_returns_the_live_slot_or_null(): void
    {
        $slot = $this->slots()->resolveSlot($this->service, $this->date, '09:15', $this->therapist->id);

        $this->assertNotNull($slot);
        $this->assertSame($this->room->id, $slot->roomId);
        $this->assertSame('10:15', $slot->end());

        // 08:30 is never offered on an empty day (the 09:15 cadence rule).
        $this->assertNull($this->slots()->resolveSlot($this->service, $this->date, '08:30', $this->therapist->id));
    }

    public function test_browse_all_unions_therapists_and_carries_room(): void
    {
        $room2 = Room::factory()->create();
        $therapist2 = StaffProfile::factory()->create(['published_at' => now()]);
        $this->service->therapists()->attach($therapist2);
        $this->schedule($therapist2, $room2, '08:00', '16:00');

        $slots = $this->slots()->availableTimes($this->service, $this->date);

        // Each offered start now appears twice (one per therapist), each carrying
        // its own room.
        $byTherapist = collect($slots)->groupBy('therapistId');
        $this->assertCount(2, $byTherapist);
        $this->assertSame($this->room->id, $byTherapist[$this->therapist->id]->first()->roomId);
        $this->assertSame($room2->id, $byTherapist[$therapist2->id]->first()->roomId);
    }

    public function test_unpublished_therapist_still_offers_slots(): void
    {
        // Publishing controls only the public team page and profile detail, not
        // bookability — the wizard offers unpublished therapists in its picker, so
        // the engine must produce their availability too (regression: a service
        // whose sole therapist is unpublished used to show an empty calendar).
        $this->therapist->update(['published_at' => null]);

        $this->assertSame(
            $this->anchored(480, 900),
            $this->startMinutes($this->slots()->availableTimes($this->service, $this->date)),
        );

        $this->assertContains(
            $this->date->toDateString(),
            $this->slots()->availableDays($this->service, $this->date->copy(), $this->date->copy()->addDays(6)),
        );

        $this->assertNotNull(
            $this->slots()->resolveSlot($this->service, $this->date, '08:00', $this->therapist->id),
        );
    }

    public function test_past_dates_offer_no_slots(): void
    {
        // Even with working time on the past date, the lead-time guard drops it all.
        $past = $this->date->copy()->subWeeks(10);
        $this->schedule($this->therapist, $this->room, '08:00', '16:00', $past);

        $this->assertSame([], $this->slots()->availableTimes($this->service, $past));
    }

    // --- Lessons occupy time too ----------------------------------------------

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function lesson(array $attributes = []): Lesson
    {
        return Lesson::factory()->create([
            'room_id' => $this->room->id,
            'lesson_date' => $this->date->toDateString(),
            'start_time' => '09:15',
            'end_time' => '10:15',
            ...$attributes,
        ]);
    }

    public function test_a_lesson_in_the_room_reshapes_offered_times_exactly_like_a_reservation(): void
    {
        // Same window as test_existing_reservation_reshapes_offered_times, so the
        // identical expectation proves lessons are busy time and not cuts: a cut
        // would restart the cadence at 10:15 and offer that minute. The 10:30
        // anchor is the lecturer's own break — every lecturer is staff, so a
        // class leaves the same rest behind it as a visit does.
        $this->lesson();

        $slots = $this->slots()->availableTimes($this->service, $this->date);

        $this->assertSame([480, ...$this->anchored(630, 900)], $this->startMinutes($slots));
    }

    public function test_a_lecturer_who_takes_no_break_frees_the_room_at_the_bell(): void
    {
        // The break behind a class belongs to whoever taught it. Set that lecturer's
        // default to nothing and the room is offerable from 10:15 — while the gap
        // before the class still follows strict gluing, which is what keeps 08:45
        // and 09:00 out of the list.
        $lecturer = StaffProfile::factory()->create(['break_blocks' => 0]);
        $this->lesson(['instructor_id' => $lecturer->user_id]);

        $slots = $this->slots()->availableTimes($this->service, $this->date);

        $this->assertSame([480, ...$this->anchored(615, 900)], $this->startMinutes($slots));
    }

    public function test_a_lesson_taught_by_the_therapist_blocks_them_in_every_room(): void
    {
        $this->lesson([
            'room_id' => Room::factory()->create()->id,
            'instructor_id' => $this->therapist->user_id,
        ]);

        $slots = $this->slots()->availableTimes($this->service, $this->date);

        $this->assertSame([480, ...$this->anchored(630, 900)], $this->startMinutes($slots));
    }

    public function test_a_lesson_elsewhere_by_an_unrelated_lecturer_changes_nothing(): void
    {
        $this->lesson(['room_id' => Room::factory()->create()->id]);

        $this->assertSame(
            $this->anchored(480, 900),
            $this->startMinutes($this->slots()->availableTimes($this->service, $this->date)),
        );
    }

    public function test_a_soft_deleted_lesson_frees_its_time_again(): void
    {
        $this->lesson()->delete();

        $this->assertSame(
            $this->anchored(480, 900),
            $this->startMinutes($this->slots()->availableTimes($this->service, $this->date)),
        );
    }

    public function test_a_day_filled_by_a_lesson_is_full_not_available(): void
    {
        $this->lesson(['start_time' => '08:00', 'end_time' => '16:00']);

        $availability = $this->slots()->dayAvailability($this->service, $this->date->copy(), $this->date->copy()->addDays(6));

        $this->assertContains($this->date->toDateString(), $availability['full']);
        $this->assertNotContains($this->date->toDateString(), $availability['available']);
    }

    public function test_therapist_has_no_opening_when_a_lesson_fills_the_day(): void
    {
        $this->assertTrue($this->slots()->therapistHasOpening($this->therapist->id, $this->date));

        $this->lesson(['start_time' => '08:00', 'end_time' => '16:00']);

        $this->assertFalse($this->slots()->therapistHasOpening($this->therapist->id, $this->date));
    }

    public function test_available_days_preloads_its_data_instead_of_querying_per_day(): void
    {
        // The range scan must stay a handful of queries whatever the horizon —
        // adding lessons to the picture must not reintroduce a per-day query.
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->slots()->availableDays($this->service, $this->date->copy(), $this->date->copy()->addDays(20));

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Two of these are the break tables, read once for the whole window by the
        // single BreakResolver the preloaded context carries.
        $this->assertLessThanOrEqual(12, $count, "availableDays ran {$count} queries over a 21-day range.");
    }
}
