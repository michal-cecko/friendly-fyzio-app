<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\StaffProfile;
use App\Models\TherapistWorkBlock;
use App\Support\CalendarAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CalendarAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_minutes_sums_work_blocks_on_the_date(): void
    {
        $date = Carbon::parse('2026-01-05');

        $t1 = StaffProfile::factory()->create();
        $t2 = StaffProfile::factory()->create();

        // T1: counts on this date (240 min).
        TherapistWorkBlock::factory()->for($t1, 'therapist')->create([
            'work_date' => $date->toDateString(), 'start_time' => '08:00', 'end_time' => '12:00',
        ]);
        // T1: different date — excluded.
        TherapistWorkBlock::factory()->for($t1, 'therapist')->create([
            'work_date' => $date->copy()->addDay()->toDateString(), 'start_time' => '08:00', 'end_time' => '18:00',
        ]);
        // T2: counts (120 min).
        TherapistWorkBlock::factory()->for($t2, 'therapist')->create([
            'work_date' => $date->toDateString(), 'start_time' => '09:00', 'end_time' => '11:00',
        ]);
        // T1: second block on the same date (60 min).
        TherapistWorkBlock::factory()->for($t1, 'therapist')->create([
            'work_date' => $date->toDateString(), 'start_time' => '13:00', 'end_time' => '14:00',
        ]);

        $availability = new CalendarAvailability;

        $this->assertSame(420, $availability->availableMinutes($date));                    // 240 + 120 + 60
        $this->assertSame(300, $availability->availableMinutes($date, [$t1->getKey()]));    // 240 + 60
        $this->assertSame(120, $availability->availableMinutes($date, [$t2->getKey()]));

        // A therapist with no work blocks (vacation = deleted rows) contributes nothing.
        $t1->workBlocks()->whereDate('work_date', $date)->delete();

        $this->assertSame(0, $availability->availableMinutes($date, [$t1->getKey()]));
        $this->assertSame(120, $availability->availableMinutes($date));                     // only T2 remains
    }

    public function test_available_minutes_can_be_scoped_to_a_room(): void
    {
        $date = Carbon::parse('2026-01-05');

        $therapist = StaffProfile::factory()->create();
        $roomA = Room::factory()->create();
        $roomB = Room::factory()->create();

        // 180 min in room A, 120 min in room B on this date.
        TherapistWorkBlock::factory()->for($therapist, 'therapist')->create([
            'room_id' => $roomA->getKey(), 'work_date' => $date->toDateString(), 'start_time' => '08:00', 'end_time' => '11:00',
        ]);
        TherapistWorkBlock::factory()->for($therapist, 'therapist')->create([
            'room_id' => $roomB->getKey(), 'work_date' => $date->toDateString(), 'start_time' => '11:00', 'end_time' => '13:00',
        ]);
        // Another 60 min in room A only.
        TherapistWorkBlock::factory()->for($therapist, 'therapist')->create([
            'room_id' => $roomA->getKey(), 'work_date' => $date->toDateString(), 'start_time' => '13:00', 'end_time' => '14:00',
        ]);

        $availability = new CalendarAvailability;

        $this->assertSame(360, $availability->availableMinutes($date));                            // 180 + 120 + 60, all rooms
        $this->assertSame(240, $availability->availableMinutes($date, [], $roomA->getKey()));       // 180 + 60 in room A
        $this->assertSame(120, $availability->availableMinutes($date, [], $roomB->getKey()));       // 120 in room B
    }
}
