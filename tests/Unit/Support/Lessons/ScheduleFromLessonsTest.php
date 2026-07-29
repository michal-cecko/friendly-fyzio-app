<?php

namespace Tests\Unit\Support\Lessons;

use App\Support\Lessons\ScheduleFromLessons;
use PHPUnit\Framework\TestCase;

/**
 * Backfilling the rozvrh out of existing lessons is what gives the imported
 * historical séries their day and time, so what it infers — and what it refuses
 * to infer — matters.
 */
class ScheduleFromLessonsTest extends TestCase
{
    /**
     * @param  array<int, array{0: string, 1: string, 2?: string}>  $rows
     */
    protected function derive(array $rows): ?string
    {
        return (new ScheduleFromLessons)->fromRows(array_map(fn (array $row): array => [
            'lesson_date' => $row[0],
            'start_time' => $row[1],
            'end_time' => $row[2] ?? '18:00:00',
        ], $rows))->label();
    }

    public function test_it_reads_a_weekly_series(): void
    {
        // Three Tuesdays.
        $this->assertSame('úterý 17:00–18:00', $this->derive([
            ['2026-08-04', '17:00:00'],
            ['2026-08-11', '17:00:00'],
            ['2026-08-18', '17:00:00'],
        ]));
    }

    public function test_it_reads_a_series_meeting_twice_a_week_at_different_times(): void
    {
        $this->assertSame('středa 09:00–10:00, čtvrtek 10:30–11:30', $this->derive([
            ['2026-08-05', '09:00:00', '10:00:00'],
            ['2026-08-06', '10:30:00', '11:30:00'],
            ['2026-08-12', '09:00:00', '10:00:00'],
            ['2026-08-13', '10:30:00', '11:30:00'],
        ]));
    }

    public function test_one_moved_lesson_does_not_become_a_weekly_term(): void
    {
        // Four Tuesdays, one of which someone moved to the Friday.
        $this->assertSame('úterý 17:00–18:00', $this->derive([
            ['2026-08-04', '17:00:00'],
            ['2026-08-11', '17:00:00'],
            ['2026-08-21', '17:00:00'],
            ['2026-08-25', '17:00:00'],
        ]));
    }

    public function test_a_series_too_short_to_repeat_keeps_what_it_has(): void
    {
        // Nothing occurs twice, so there is nothing better to infer from.
        $this->assertSame('úterý 17:00–18:00', $this->derive([
            ['2026-08-04', '17:00:00'],
        ]));
    }

    public function test_no_lessons_means_no_schedule(): void
    {
        $this->assertTrue((new ScheduleFromLessons)->fromRows([])->isEmpty());
    }
}
