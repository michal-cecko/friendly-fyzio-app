<?php

namespace Tests\Unit;

use App\Enums\DayOfWeek;
use App\Enums\WeekType;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class CalendarEnumDatesTest extends TestCase
{
    public function test_day_of_week_maps_each_day_of_a_week(): void
    {
        $monday = Carbon::parse('2026-01-05')->startOfWeek(Carbon::MONDAY);

        $expected = [
            DayOfWeek::Monday,
            DayOfWeek::Tuesday,
            DayOfWeek::Wednesday,
            DayOfWeek::Thursday,
            DayOfWeek::Friday,
            DayOfWeek::Saturday,
            DayOfWeek::Sunday,
        ];

        foreach ($expected as $offset => $case) {
            $this->assertSame($case, DayOfWeek::fromCarbon($monday->copy()->addDays($offset)));
        }
    }

    public function test_week_type_for_date_follows_iso_week_parity(): void
    {
        $date = Carbon::parse('2026-01-05')->startOfWeek(Carbon::MONDAY);

        $this->assertSame(
            $date->isoWeek() % 2 === 0 ? WeekType::Even : WeekType::Odd,
            WeekType::forDate($date),
        );

        // Consecutive ISO weeks always flip parity.
        $this->assertNotSame(WeekType::forDate($date), WeekType::forDate($date->copy()->addWeek()));
    }

    public function test_matches_date_handles_all_and_parity(): void
    {
        $date = Carbon::parse('2026-01-05')->startOfWeek(Carbon::MONDAY);
        $parity = WeekType::forDate($date);

        $this->assertTrue(WeekType::All->matchesDate($date));
        $this->assertTrue($parity->matchesDate($date));
        $this->assertFalse($parity->matchesDate($date->copy()->addWeek()));
    }
}
