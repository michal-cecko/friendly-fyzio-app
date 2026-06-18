<?php

namespace Tests\Feature;

use App\Enums\DayOfWeek;
use App\Enums\WeekType;
use App\Models\CalendarBlock;
use App\Models\TherapistNonstandardDate;
use App\Models\TherapistProfile;
use App\Models\TherapistWeeklySchedule;
use App\Support\CalendarAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CalendarAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_minutes_sums_schedules_and_nonstandard_minus_blocks(): void
    {
        $date = Carbon::parse('2026-01-05')->startOfWeek(Carbon::MONDAY);
        $opposite = WeekType::forDate($date) === WeekType::Odd ? WeekType::Even : WeekType::Odd;

        $t1 = TherapistProfile::factory()->create();
        $t2 = TherapistProfile::factory()->create();

        // T1: counts on this Monday (240 min).
        TherapistWeeklySchedule::factory()->for($t1, 'therapist')->create([
            'day_of_week' => DayOfWeek::Monday, 'week_type' => WeekType::All, 'start_time' => '08:00', 'end_time' => '12:00',
        ]);
        // T1: opposite-parity week — excluded on this date.
        TherapistWeeklySchedule::factory()->for($t1, 'therapist')->create([
            'day_of_week' => DayOfWeek::Monday, 'week_type' => $opposite, 'start_time' => '08:00', 'end_time' => '12:00',
        ]);
        // T1: different weekday — excluded.
        TherapistWeeklySchedule::factory()->for($t1, 'therapist')->create([
            'day_of_week' => DayOfWeek::Tuesday, 'week_type' => WeekType::All, 'start_time' => '08:00', 'end_time' => '18:00',
        ]);
        // T2: counts (120 min).
        TherapistWeeklySchedule::factory()->for($t2, 'therapist')->create([
            'day_of_week' => DayOfWeek::Monday, 'week_type' => WeekType::All, 'start_time' => '09:00', 'end_time' => '11:00',
        ]);
        // T1: one-off extra hour on this date (60 min).
        TherapistNonstandardDate::factory()->for($t1, 'therapist')->create([
            'work_date' => $date->toDateString(), 'start_time' => '13:00', 'end_time' => '14:00',
        ]);

        $availability = new CalendarAvailability;

        $this->assertSame(420, $availability->availableMinutes($date));                    // 240 + 120 + 60
        $this->assertSame(300, $availability->availableMinutes($date, [$t1->getKey()]));    // 240 + 60
        $this->assertSame(120, $availability->availableMinutes($date, [$t2->getKey()]));

        // A multi-day block wipes out T1's whole day.
        CalendarBlock::factory()->for($t1, 'therapist')->create([
            'start_date' => $date->toDateString(),
            'end_date' => $date->toDateString(),
        ]);

        $this->assertSame(0, $availability->availableMinutes($date, [$t1->getKey()]));
        $this->assertSame(120, $availability->availableMinutes($date));                     // only T2 remains
    }
}
