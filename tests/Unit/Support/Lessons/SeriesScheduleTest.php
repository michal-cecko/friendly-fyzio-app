<?php

namespace Tests\Unit\Support\Lessons;

use App\Enums\DayOfWeek;
use App\Support\Lessons\ScheduleSlot;
use App\Support\Lessons\SeriesSchedule;
use PHPUnit\Framework\TestCase;

/**
 * The rozvrh one-liner is what clients read on the course page ("úterý 17:30"),
 * so its wording is the point of these tests, not an implementation detail.
 */
class SeriesScheduleTest extends TestCase
{
    /**
     * @param  array<int, array{day: DayOfWeek, start: string, end: string, room?: string}>  $rows
     */
    protected function schedule(array $rows): SeriesSchedule
    {
        return SeriesSchedule::fromArray(array_map(fn (array $row): array => [
            'day' => $row['day']->value,
            'start_time' => $row['start'],
            'end_time' => $row['end'],
            'room_id' => $row['room'] ?? null,
        ], $rows));
    }

    public function test_a_single_day_reads_as_day_and_time(): void
    {
        $schedule = $this->schedule([
            ['day' => DayOfWeek::Tuesday, 'start' => '17:30', 'end' => '18:30'],
        ]);

        $this->assertSame('úterý 17:30–18:30', $schedule->label());
        $this->assertSame('út 17:30', $schedule->shortLabel());
    }

    public function test_days_sharing_a_time_are_joined_into_one_phrase(): void
    {
        $schedule = $this->schedule([
            ['day' => DayOfWeek::Thursday, 'start' => '17:30', 'end' => '18:30'],
            ['day' => DayOfWeek::Tuesday, 'start' => '17:30', 'end' => '18:30'],
        ]);

        // Sorted pondělí → neděle, whatever order they were typed in.
        $this->assertSame('úterý a čtvrtek 17:30–18:30', $schedule->label());

        // The short label never groups: the card lists every slot on its own.
        $this->assertSame('út 17:30, čt 17:30', $schedule->shortLabel());
    }

    public function test_three_days_sharing_a_time_use_commas_and_a_final_a(): void
    {
        $schedule = $this->schedule([
            ['day' => DayOfWeek::Monday, 'start' => '07:00', 'end' => '08:00'],
            ['day' => DayOfWeek::Wednesday, 'start' => '07:00', 'end' => '08:00'],
            ['day' => DayOfWeek::Friday, 'start' => '07:00', 'end' => '08:00'],
        ]);

        $this->assertSame('pondělí, středa a pátek 07:00–08:00', $schedule->label());
    }

    public function test_days_with_different_times_stay_separate(): void
    {
        $schedule = $this->schedule([
            ['day' => DayOfWeek::Wednesday, 'start' => '09:00', 'end' => '10:00'],
            ['day' => DayOfWeek::Thursday, 'start' => '10:30', 'end' => '11:30'],
        ]);

        $this->assertSame('středa 09:00–10:00, čtvrtek 10:30–11:30', $schedule->label());
        $this->assertSame('st 09:00, čt 10:30', $schedule->shortLabel());
    }

    public function test_the_same_day_twice_lists_both_times(): void
    {
        $schedule = $this->schedule([
            ['day' => DayOfWeek::Monday, 'start' => '17:00', 'end' => '18:00'],
            ['day' => DayOfWeek::Monday, 'start' => '09:00', 'end' => '10:00'],
        ]);

        $this->assertSame('pondělí 09:00–10:00, pondělí 17:00–18:00', $schedule->label());
    }

    public function test_an_empty_schedule_has_no_label(): void
    {
        $this->assertTrue(SeriesSchedule::fromArray(null)->isEmpty());
        $this->assertNull(SeriesSchedule::fromArray([])->label());
        $this->assertNull(SeriesSchedule::fromArray([])->shortLabel());
    }

    public function test_unusable_rows_are_dropped_rather_than_thrown_on(): void
    {
        $schedule = SeriesSchedule::fromArray([
            ['day' => 'streda', 'start_time' => '17:00', 'end_time' => '18:00'],
            ['day' => DayOfWeek::Monday->value, 'start_time' => null, 'end_time' => '18:00'],
            ['day' => DayOfWeek::Monday->value, 'start_time' => '17:00', 'end_time' => '18:00'],
            'nonsense',
        ]);

        $this->assertSame('pondělí 17:00–18:00', $schedule->label());
    }

    public function test_duplicate_slots_collapse(): void
    {
        $schedule = SeriesSchedule::fromSlots([
            new ScheduleSlot(DayOfWeek::Tuesday, '17:30', '18:30', 'room-a'),
            // A série cannot be in two rooms at once, so this is the same slot and
            // the first one's room wins.
            new ScheduleSlot(DayOfWeek::Tuesday, '17:30', '18:30', 'room-b'),
        ]);

        $this->assertCount(1, $schedule->slots());
        $this->assertSame([
            ['day' => 'tuesday', 'start_time' => '17:30', 'end_time' => '18:30', 'room_id' => 'room-a'],
        ], $schedule->toArray());
    }

    /**
     * Staff read the rozvrh with the room in it, and the room may differ per day —
     * that is the whole point of it living on the slot.
     */
    public function test_days_sharing_a_time_and_a_room_are_joined_into_one_phrase(): void
    {
        $schedule = $this->schedule([
            ['day' => DayOfWeek::Monday, 'start' => '17:00', 'end' => '18:00', 'room' => 'velka'],
            ['day' => DayOfWeek::Wednesday, 'start' => '17:00', 'end' => '18:00', 'room' => 'velka'],
        ]);

        $this->assertSame(
            'pondělí a středa 17:00–18:00 · Velká tělocvična',
            $schedule->labelWithRooms(['velka' => 'Velká tělocvična']),
        );

        // Clients get the same rozvrh without the room.
        $this->assertSame('pondělí a středa 17:00–18:00', $schedule->label());
    }

    public function test_days_in_different_rooms_stay_separate(): void
    {
        $schedule = $this->schedule([
            ['day' => DayOfWeek::Monday, 'start' => '17:00', 'end' => '18:00', 'room' => 'velka'],
            ['day' => DayOfWeek::Wednesday, 'start' => '17:00', 'end' => '18:00', 'room' => 'mala'],
        ]);

        $this->assertSame(
            'pondělí 17:00–18:00 · Velká tělocvična, středa 17:00–18:00 · Malá tělocvična',
            $schedule->labelWithRooms(['velka' => 'Velká tělocvična', 'mala' => 'Malá tělocvična']),
        );
    }

    public function test_a_slot_whose_room_is_unknown_reads_as_the_plain_label(): void
    {
        $schedule = $this->schedule([
            ['day' => DayOfWeek::Monday, 'start' => '17:00', 'end' => '18:00'],
            // Filled in, but the room has since been deleted.
            ['day' => DayOfWeek::Wednesday, 'start' => '09:00', 'end' => '10:00', 'room' => 'gone'],
        ]);

        $this->assertSame('pondělí 17:00–18:00, středa 09:00–10:00', $schedule->labelWithRooms([]));
        $this->assertNull(SeriesSchedule::fromArray([])->labelWithRooms([]));
    }

    /**
     * A lesson cannot be saved without a room, so generation waits until every
     * slot names one — while the rozvrh itself still holds together without.
     */
    public function test_a_slot_without_a_room_is_kept_but_marks_the_schedule_incomplete(): void
    {
        $incomplete = $this->schedule([
            ['day' => DayOfWeek::Monday, 'start' => '17:00', 'end' => '18:00', 'room' => 'velka'],
            ['day' => DayOfWeek::Wednesday, 'start' => '17:00', 'end' => '18:00'],
        ]);

        $this->assertCount(2, $incomplete->slots());
        $this->assertFalse($incomplete->everySlotHasRoom());
        $this->assertSame(['velka'], $incomplete->roomIds());

        $complete = $this->schedule([
            ['day' => DayOfWeek::Monday, 'start' => '17:00', 'end' => '18:00', 'room' => 'velka'],
            ['day' => DayOfWeek::Wednesday, 'start' => '17:00', 'end' => '18:00', 'room' => 'velka'],
        ]);

        $this->assertTrue($complete->everySlotHasRoom());
        // One room named twice is one room to look up.
        $this->assertSame(['velka'], $complete->roomIds());
    }

    public function test_stored_seconds_are_trimmed_to_hours_and_minutes(): void
    {
        $schedule = SeriesSchedule::fromArray([
            ['day' => DayOfWeek::Friday->value, 'start_time' => '07:00:00', 'end_time' => '08:15:00'],
        ]);

        $this->assertSame('pátek 07:00–08:15', $schedule->label());
    }
}
