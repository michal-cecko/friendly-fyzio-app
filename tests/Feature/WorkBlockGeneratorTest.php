<?php

namespace Tests\Feature;

use App\Enums\DayOfWeek;
use App\Enums\WeekType;
use App\Models\TherapistProfile;
use App\Models\TherapistWorkBlock;
use App\Models\TherapistWorkBlockSeries;
use App\Support\WorkBlocks\WorkBlockGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkBlockGeneratorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 2026-01-05 is a Monday in ISO week 2 (even); 2026-01-12 is week 3 (odd).
     */
    public function test_occurrence_dates_for_each_week_type(): void
    {
        $generator = new WorkBlockGenerator;

        $from = CarbonImmutable::parse('2026-01-05');
        $until = CarbonImmutable::parse('2026-02-01');

        $this->assertSame(WeekType::Even, WeekType::forDate($from));

        $weekly = $generator->occurrenceDates(DayOfWeek::Monday, WeekType::All, $from, $until);
        $this->assertSame(
            ['2026-01-05', '2026-01-12', '2026-01-19', '2026-01-26'],
            array_map(fn (CarbonImmutable $date): string => $date->toDateString(), $weekly),
        );

        $even = $generator->occurrenceDates(DayOfWeek::Monday, WeekType::Even, $from, $until);
        $this->assertSame(
            ['2026-01-05', '2026-01-19'],
            array_map(fn (CarbonImmutable $date): string => $date->toDateString(), $even),
        );

        $odd = $generator->occurrenceDates(DayOfWeek::Monday, WeekType::Odd, $from, $until);
        $this->assertSame(
            ['2026-01-12', '2026-01-26'],
            array_map(fn (CarbonImmutable $date): string => $date->toDateString(), $odd),
        );
    }

    public function test_occurrence_dates_start_on_the_first_matching_weekday(): void
    {
        $generator = new WorkBlockGenerator;

        // From a Wednesday, looking for Mondays: first hit is the following week.
        $dates = $generator->occurrenceDates(
            DayOfWeek::Monday,
            WeekType::All,
            CarbonImmutable::parse('2026-01-07'),
            CarbonImmutable::parse('2026-01-19'),
        );

        $this->assertSame(
            ['2026-01-12', '2026-01-19'],
            array_map(fn (CarbonImmutable $date): string => $date->toDateString(), $dates),
        );
    }

    public function test_materialize_creates_rows_advances_cursor_and_is_idempotent(): void
    {
        $series = TherapistWorkBlockSeries::factory()->create([
            'day_of_week' => DayOfWeek::Monday,
            'week_type' => WeekType::All,
            'start_time' => '08:00',
            'end_time' => '12:00',
            'starts_on' => '2026-01-05',
            'ends_on' => null,
            'generated_until' => '2026-01-04',
        ]);

        $result = app(WorkBlockGenerator::class)->materialize($series, CarbonImmutable::parse('2026-01-31'));

        $this->assertSame(['created' => 4, 'skipped' => 0], $result);
        $this->assertSame('2026-01-31', $series->fresh()->generated_until->toDateString());

        $blocks = $series->blocks()->orderBy('work_date')->get();
        $this->assertSame(
            ['2026-01-05', '2026-01-12', '2026-01-19', '2026-01-26'],
            $blocks->map(fn (TherapistWorkBlock $block): string => $block->work_date->toDateString())->all(),
        );
        $this->assertSame($series->therapist_id, $blocks->first()->therapist_id);
        $this->assertSame($series->room_id, $blocks->first()->room_id);

        // Re-running to the same horizon creates nothing new.
        $again = app(WorkBlockGenerator::class)->materialize($series->fresh(), CarbonImmutable::parse('2026-01-31'));
        $this->assertSame(['created' => 0, 'skipped' => 0], $again);
        $this->assertSame(4, $series->blocks()->count());
    }

    public function test_materialize_respects_ends_on(): void
    {
        $series = TherapistWorkBlockSeries::factory()->create([
            'day_of_week' => DayOfWeek::Monday,
            'week_type' => WeekType::All,
            'starts_on' => '2026-01-05',
            'ends_on' => '2026-01-18',
            'generated_until' => '2026-01-04',
        ]);

        $result = app(WorkBlockGenerator::class)->materialize($series, CarbonImmutable::parse('2026-03-31'));

        $this->assertSame(['created' => 2, 'skipped' => 0], $result);
        $this->assertSame('2026-01-18', $series->fresh()->generated_until->toDateString());
        $this->assertSame(
            ['2026-01-05', '2026-01-12'],
            $series->blocks()->orderBy('work_date')->get()
                ->map(fn (TherapistWorkBlock $block): string => $block->work_date->toDateString())->all(),
        );
    }

    public function test_materialize_skips_dates_with_overlapping_blocks(): void
    {
        $therapist = TherapistProfile::factory()->create();

        // Existing one-off block overlapping the series interval on 2026-01-12.
        TherapistWorkBlock::factory()->for($therapist, 'therapist')->create([
            'work_date' => '2026-01-12',
            'start_time' => '10:00',
            'end_time' => '14:00',
        ]);
        // Non-overlapping block on 2026-01-19 (afternoon only) — no conflict.
        TherapistWorkBlock::factory()->for($therapist, 'therapist')->create([
            'work_date' => '2026-01-19',
            'start_time' => '13:00',
            'end_time' => '15:00',
        ]);

        $series = TherapistWorkBlockSeries::factory()->for($therapist, 'therapist')->create([
            'day_of_week' => DayOfWeek::Monday,
            'week_type' => WeekType::All,
            'start_time' => '08:00',
            'end_time' => '12:00',
            'starts_on' => '2026-01-05',
            'ends_on' => null,
            'generated_until' => '2026-01-04',
        ]);

        $result = app(WorkBlockGenerator::class)->materialize($series, CarbonImmutable::parse('2026-01-31'));

        $this->assertSame(['created' => 3, 'skipped' => 1], $result);
        $this->assertSame(
            ['2026-01-05', '2026-01-19', '2026-01-26'],
            $series->blocks()->orderBy('work_date')->get()
                ->map(fn (TherapistWorkBlock $block): string => $block->work_date->toDateString())->all(),
        );
    }

    public function test_materialize_never_regenerates_deleted_occurrences(): void
    {
        $series = TherapistWorkBlockSeries::factory()->create([
            'day_of_week' => DayOfWeek::Monday,
            'week_type' => WeekType::All,
            'starts_on' => '2026-01-05',
            'ends_on' => null,
            'generated_until' => '2026-01-04',
        ]);

        $generator = app(WorkBlockGenerator::class);
        $generator->materialize($series, CarbonImmutable::parse('2026-01-31'));

        // Vacation: delete the 2026-01-12 occurrence.
        $series->blocks()->whereDate('work_date', '2026-01-12')->delete();

        $result = $generator->materialize($series->fresh(), CarbonImmutable::parse('2026-02-28'));

        $this->assertSame(0, $series->blocks()->whereDate('work_date', '2026-01-12')->count());
        $this->assertSame(['created' => 4, 'skipped' => 0], $result); // Feb 2, 9, 16, 23 only
    }
}
