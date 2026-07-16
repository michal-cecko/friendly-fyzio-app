<?php

namespace Tests\Feature;

use App\Enums\DayOfWeek;
use App\Enums\WeekType;
use App\Models\TherapistWorkBlockSeries;
use App\Support\WorkBlocks\WorkBlockGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ExtendWorkBlockSeriesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_ended_series_is_extended_to_the_horizon(): void
    {
        $startsOn = Carbon::today();

        $series = TherapistWorkBlockSeries::factory()->create([
            'day_of_week' => DayOfWeek::fromCarbon($startsOn),
            'week_type' => WeekType::All,
            'starts_on' => $startsOn->toDateString(),
            'ends_on' => null,
            'generated_until' => $startsOn->copy()->subDay()->toDateString(),
        ]);

        $this->artisan('work-blocks:extend')->assertSuccessful();

        $horizon = WorkBlockGenerator::horizon();

        $this->assertSame($horizon->toDateString(), $series->fresh()->generated_until->toDateString());
        // One occurrence per week from starts_on through the horizon.
        $expected = intdiv((int) $startsOn->diffInDays($horizon), 7) + 1;
        $this->assertSame($expected, $series->blocks()->count());
        $this->assertSame(
            $startsOn->toDateString(),
            $series->blocks()->orderBy('work_date')->first()->work_date->toDateString(),
        );
    }

    public function test_ended_series_is_not_extended_past_ends_on(): void
    {
        $startsOn = Carbon::today();
        $endsOn = $startsOn->copy()->addWeeks(2);

        $series = TherapistWorkBlockSeries::factory()->create([
            'day_of_week' => DayOfWeek::fromCarbon($startsOn),
            'week_type' => WeekType::All,
            'starts_on' => $startsOn->toDateString(),
            'ends_on' => $endsOn->toDateString(),
            'generated_until' => $startsOn->copy()->subDay()->toDateString(),
        ]);

        $this->artisan('work-blocks:extend')->assertSuccessful();

        $this->assertSame($endsOn->toDateString(), $series->fresh()->generated_until->toDateString());
        $this->assertSame(3, $series->blocks()->count());

        // A fully generated ended series is left alone on the next run.
        $this->artisan('work-blocks:extend')->assertSuccessful();
        $this->assertSame(3, $series->fresh()->blocks()->count());
    }

    public function test_deleted_occurrences_are_not_recreated(): void
    {
        $startsOn = Carbon::today();

        $series = TherapistWorkBlockSeries::factory()->create([
            'day_of_week' => DayOfWeek::fromCarbon($startsOn),
            'week_type' => WeekType::All,
            'starts_on' => $startsOn->toDateString(),
            'ends_on' => null,
            'generated_until' => $startsOn->copy()->subDay()->toDateString(),
        ]);

        $this->artisan('work-blocks:extend')->assertSuccessful();

        // Vacation: remove next week's occurrence.
        $vacationDate = $startsOn->copy()->addWeek()->toDateString();
        $series->blocks()->whereDate('work_date', $vacationDate)->delete();

        $this->artisan('work-blocks:extend')->assertSuccessful();

        $this->assertSame(0, $series->blocks()->whereDate('work_date', $vacationDate)->count());
    }
}
