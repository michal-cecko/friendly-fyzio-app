<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Enums\ServiceType;
use App\Enums\ServiceVisibility;
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
use Tests\TestCase;

class ReservationSlotsTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $date;

    private Room $room;

    private Service $service;

    private StaffProfile $therapist;

    protected function setUp(): void
    {
        parent::setUp();

        // A Monday safely in the future so the lead-time guard never drops slots.
        $this->date = Carbon::today()->addWeeks(8)->startOfWeek(Carbon::MONDAY);
        $this->room = Room::factory()->create();

        $category = ServiceCategory::factory()->create(['type' => ServiceType::Massage]);

        // Public 60-minute service under test, with 30/90 siblings that define the
        // category's chainable + public duration sets (30 is non-public).
        $this->service = Service::factory()->create([
            'category_id' => $category->id,
            'duration_minutes' => 60,
            'break_minutes' => 15,
            'visibility' => ServiceVisibility::Public,
            'published_at' => now(),
        ]);
        Service::factory()->create(['category_id' => $category->id, 'duration_minutes' => 30, 'break_minutes' => 15, 'visibility' => ServiceVisibility::Clients, 'published_at' => now()]);
        Service::factory()->create(['category_id' => $category->id, 'duration_minutes' => 90, 'break_minutes' => 15, 'visibility' => ServiceVisibility::Public, 'published_at' => now()]);

        $this->therapist = StaffProfile::factory()->create(['published_at' => now()]);
        $this->service->therapists()->attach($this->therapist);

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

    /** @return array<int, int> */
    private function every15(int $from, int $to): array
    {
        $values = [];
        for ($minute = $from; $minute <= $to; $minute += 15) {
            $values[] = $minute;
        }

        return $values;
    }

    private function slots(): ReservationSlots
    {
        return app(ReservationSlots::class);
    }

    public function test_available_times_on_an_empty_day_match_the_gap_fill_rules(): void
    {
        $slots = $this->slots()->availableTimes($this->service, $this->date);

        // 60-minute empty-day offering: 08:00, then 09:15…15:00.
        $this->assertSame([480, ...$this->every15(555, 900)], $this->startMinutes($slots));

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

        $this->assertSame([480, ...$this->every15(630, 900)], $this->startMinutes($slots));
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

        $this->assertSame([480, ...$this->every15(630, 900)], $this->startMinutes($slots));
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

        $this->assertSame([480, ...$this->every15(555, 900)], $this->startMinutes($slots));
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
        $this->assertSame([720, ...$this->every15(795, 900)], $this->startMinutes($slots));
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
            [480, ...$this->every15(555, 900)],
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
}
