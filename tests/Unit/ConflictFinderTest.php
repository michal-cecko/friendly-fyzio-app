<?php

namespace Tests\Unit;

use App\Enums\ConflictSeverity;
use App\Enums\DayOfWeek;
use App\Enums\ReservationStatus;
use App\Enums\WeekType;
use App\Models\Lesson;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomBlocking;
use App\Models\StaffProfile;
use App\Models\TherapistWorkBlock;
use App\Models\User;
use App\Support\Reservations\Conflict;
use App\Support\Reservations\ConflictFinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ConflictFinderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function reservation(array $attributes): Reservation
    {
        return Reservation::factory()->create([
            'reservation_date' => Carbon::today()->toDateString(),
            'status' => ReservationStatus::Confirmed,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function lesson(array $attributes): Lesson
    {
        return Lesson::factory()->create([
            'lesson_date' => Carbon::today()->toDateString(),
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function workBlock(array $attributes): TherapistWorkBlock
    {
        return TherapistWorkBlock::factory()->create([
            'work_date' => Carbon::today()->toDateString(),
            ...$attributes,
        ]);
    }

    /**
     * @param  list<Conflict>  $conflicts
     * @return list<Conflict>
     */
    private function ofType(array $conflicts, string $type): array
    {
        return array_values(array_filter($conflicts, fn (Conflict $conflict): bool => $conflict->type === $type));
    }

    // --- Reservation vs reservation (the original behaviour) -------------------

    public function test_overlapping_reservations_in_the_same_room_conflict(): void
    {
        $room = Room::factory()->create();
        $this->reservation(['room_id' => $room->id, 'start_time' => '09:00', 'end_time' => '10:00']);
        $this->reservation(['room_id' => $room->id, 'start_time' => '09:30', 'end_time' => '10:30']);

        $conflicts = $this->ofType(ConflictFinder::upcoming(), 'room');

        $this->assertCount(1, $conflicts);
        $this->assertSame('Dvojí rezervace místnosti', $conflicts[0]->title);
        $this->assertSame(ConflictSeverity::Hard, $conflicts[0]->severity);
        $this->assertSame('09:00–10:00', $conflicts[0]->a->time);
        $this->assertSame('09:30–10:30', $conflicts[0]->b->time);
    }

    public function test_touching_intervals_do_not_conflict(): void
    {
        $room = Room::factory()->create();
        $this->reservation(['room_id' => $room->id, 'start_time' => '09:00', 'end_time' => '10:00']);
        $this->reservation(['room_id' => $room->id, 'start_time' => '10:00', 'end_time' => '11:00']);

        $this->assertSame([], ConflictFinder::upcoming());
    }

    public function test_same_time_different_rooms_do_not_conflict_on_room(): void
    {
        $this->reservation(['room_id' => Room::factory()->create()->id, 'start_time' => '09:00', 'end_time' => '10:00']);
        $this->reservation(['room_id' => Room::factory()->create()->id, 'start_time' => '09:00', 'end_time' => '10:00']);

        $this->assertCount(0, $this->ofType(ConflictFinder::upcoming(), 'room'));
    }

    public function test_same_therapist_overlap_is_a_therapist_conflict(): void
    {
        $therapist = StaffProfile::factory()->create();
        // Different rooms so only the therapist dimension can conflict.
        $this->reservation(['therapist_id' => $therapist->id, 'room_id' => Room::factory()->create()->id, 'start_time' => '09:00', 'end_time' => '10:00']);
        $this->reservation(['therapist_id' => $therapist->id, 'room_id' => Room::factory()->create()->id, 'start_time' => '09:30', 'end_time' => '10:30']);

        $conflicts = ConflictFinder::upcoming();

        $this->assertCount(1, $this->ofType($conflicts, 'therapist'));
        $this->assertCount(0, $this->ofType($conflicts, 'room'));
        $this->assertSame('Dvojí rezervace terapeuta', $this->ofType($conflicts, 'therapist')[0]->title);
    }

    public function test_a_long_reservation_overlapping_two_later_ones_yields_two_conflicts(): void
    {
        $room = Room::factory()->create();
        $this->reservation(['room_id' => $room->id, 'start_time' => '09:00', 'end_time' => '12:00']);
        $this->reservation(['room_id' => $room->id, 'start_time' => '10:00', 'end_time' => '10:30']);
        $this->reservation(['room_id' => $room->id, 'start_time' => '11:00', 'end_time' => '11:30']);

        $this->assertCount(2, $this->ofType(ConflictFinder::upcoming(), 'room'));
    }

    public function test_cancelled_reservations_are_excluded(): void
    {
        $room = Room::factory()->create();
        $this->reservation(['room_id' => $room->id, 'start_time' => '09:00', 'end_time' => '10:00']);
        $this->reservation(['room_id' => $room->id, 'start_time' => '09:30', 'end_time' => '10:30', 'status' => ReservationStatus::Cancelled]);

        $this->assertSame([], ConflictFinder::upcoming());
    }

    public function test_reservations_outside_the_window_are_excluded(): void
    {
        $room = Room::factory()->create();
        $this->reservation(['room_id' => $room->id, 'reservation_date' => Carbon::today()->addDays(30)->toDateString(), 'start_time' => '09:00', 'end_time' => '10:00']);
        $this->reservation(['room_id' => $room->id, 'reservation_date' => Carbon::today()->addDays(30)->toDateString(), 'start_time' => '09:30', 'end_time' => '10:30']);

        $this->assertSame([], ConflictFinder::upcoming(7));
    }

    // --- Work block vs lesson --------------------------------------------------

    public function test_a_lesson_taught_inside_the_lecturers_own_working_hours_conflicts(): void
    {
        $therapist = StaffProfile::factory()->create();
        // Different rooms, so only the therapist dimension can fire.
        $this->workBlock(['therapist_id' => $therapist->id, 'room_id' => Room::factory()->create()->id, 'start_time' => '08:00', 'end_time' => '16:00']);
        $this->lesson(['instructor_id' => $therapist->user_id, 'room_id' => Room::factory()->create()->id, 'start_time' => '10:00', 'end_time' => '11:00']);

        $conflicts = ConflictFinder::upcoming();

        $this->assertCount(1, $this->ofType($conflicts, 'therapist'));
        $this->assertCount(0, $this->ofType($conflicts, 'room'));

        $conflict = $this->ofType($conflicts, 'therapist')[0];
        $this->assertSame('Lektor učí ve své pracovní době', $conflict->title);
        $this->assertSame(ConflictSeverity::Hard, $conflict->severity);
        $this->assertSame('workBlock', $conflict->a->kind);
        $this->assertSame('lesson', $conflict->b->kind);
    }

    public function test_a_lesson_occupying_a_room_in_working_hours_conflicts_on_the_room(): void
    {
        $room = Room::factory()->create();
        $this->workBlock(['room_id' => $room->id, 'start_time' => '08:00', 'end_time' => '16:00']);
        // An unrelated lecturer, so only the room dimension can fire.
        $this->lesson(['room_id' => $room->id, 'start_time' => '10:00', 'end_time' => '11:00']);

        $conflicts = ConflictFinder::upcoming();

        $this->assertCount(1, $this->ofType($conflicts, 'room'));
        $this->assertCount(0, $this->ofType($conflicts, 'therapist'));
        $this->assertSame('Lekce zabírá místnost v pracovní době', $this->ofType($conflicts, 'room')[0]->title);
    }

    public function test_a_lecturer_without_a_staff_profile_never_conflicts_on_the_therapist(): void
    {
        // The instructor→profile bridge is what makes therapist conflicts possible;
        // a lecturer-only account has no profile and can only clash over a room.
        $this->workBlock(['room_id' => Room::factory()->create()->id, 'start_time' => '08:00', 'end_time' => '16:00']);
        $this->lesson([
            'instructor_id' => User::factory()->lecturer()->create()->id,
            'room_id' => Room::factory()->create()->id,
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        $this->assertSame([], ConflictFinder::upcoming());
    }

    public function test_a_soft_deleted_lesson_never_conflicts(): void
    {
        $room = Room::factory()->create();
        $this->workBlock(['room_id' => $room->id, 'start_time' => '08:00', 'end_time' => '16:00']);
        $this->lesson(['room_id' => $room->id, 'start_time' => '10:00', 'end_time' => '11:00'])->delete();

        $this->assertSame([], ConflictFinder::upcoming());
    }

    public function test_a_lesson_only_touching_a_reservation_does_not_conflict(): void
    {
        $room = Room::factory()->create();
        $this->reservation(['room_id' => $room->id, 'start_time' => '10:00', 'end_time' => '11:00']);
        $this->lesson(['room_id' => $room->id, 'start_time' => '09:00', 'end_time' => '10:00']);

        $this->assertSame([], ConflictFinder::upcoming());
    }

    // --- Reservation vs lesson -------------------------------------------------

    public function test_a_reservation_overlapping_a_lesson_in_the_same_room_conflicts(): void
    {
        $room = Room::factory()->create();
        $this->reservation(['room_id' => $room->id, 'start_time' => '10:00', 'end_time' => '11:00']);
        $this->lesson(['room_id' => $room->id, 'start_time' => '10:30', 'end_time' => '12:00']);

        $conflicts = $this->ofType(ConflictFinder::upcoming(), 'room');

        $this->assertCount(1, $conflicts);
        $this->assertSame('Rezervace a lekce ve stejné místnosti', $conflicts[0]->title);
    }

    public function test_a_therapist_booked_while_teaching_conflicts(): void
    {
        $therapist = StaffProfile::factory()->create();
        $this->reservation([
            'therapist_id' => $therapist->id,
            'room_id' => Room::factory()->create()->id,
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);
        $this->lesson([
            'instructor_id' => $therapist->user_id,
            'room_id' => Room::factory()->create()->id,
            'start_time' => '10:30',
            'end_time' => '12:00',
        ]);

        $conflicts = $this->ofType(ConflictFinder::upcoming(), 'therapist');

        $this->assertCount(1, $conflicts);
        $this->assertSame('Rezervace a lekce ve stejný čas', $conflicts[0]->title);
    }

    // --- Room blockings --------------------------------------------------------

    public function test_a_reservation_inside_a_recurring_blocking_conflicts(): void
    {
        $room = Room::factory()->create();
        RoomBlocking::factory()->recurring()->create([
            'room_id' => $room->id,
            'day_of_week' => DayOfWeek::fromCarbon(Carbon::today()),
            'week_type' => WeekType::All,
            'start_time' => '16:00',
            'end_time' => '18:00',
        ]);
        $this->reservation(['room_id' => $room->id, 'start_time' => '17:00', 'end_time' => '17:30']);

        $conflicts = $this->ofType(ConflictFinder::upcoming(0), 'room');

        $this->assertCount(1, $conflicts);
        $this->assertSame('Rezervace v blokované místnosti', $conflicts[0]->title);
        $this->assertSame(ConflictSeverity::Hard, $conflicts[0]->severity);
    }

    public function test_a_recurring_blocking_on_another_week_parity_does_not_conflict(): void
    {
        $room = Room::factory()->create();
        $today = Carbon::today();
        RoomBlocking::factory()->recurring()->create([
            'room_id' => $room->id,
            'day_of_week' => DayOfWeek::fromCarbon($today),
            // The parity this week is not — so the rule must not fire today.
            'week_type' => WeekType::forDate($today) === WeekType::Odd ? WeekType::Even : WeekType::Odd,
            'start_time' => '16:00',
            'end_time' => '18:00',
        ]);
        $this->reservation(['room_id' => $room->id, 'start_time' => '17:00', 'end_time' => '17:30']);

        $this->assertSame([], ConflictFinder::upcoming(0));
    }

    public function test_a_one_off_blocking_spanning_midnight_conflicts_on_each_day_it_touches(): void
    {
        $room = Room::factory()->create();
        RoomBlocking::factory()->create([
            'room_id' => $room->id,
            'reason' => 'Pronájem',
            'start_at' => Carbon::today()->setTime(23, 0),
            'end_at' => Carbon::tomorrow()->setTime(2, 0),
        ]);
        $this->reservation(['room_id' => $room->id, 'start_time' => '23:15', 'end_time' => '23:45']);
        $this->reservation([
            'reservation_date' => Carbon::tomorrow()->toDateString(),
            'room_id' => $room->id,
            'start_time' => '01:00',
            'end_time' => '01:30',
        ]);

        $conflicts = $this->ofType(ConflictFinder::upcoming(1), 'room');

        $this->assertCount(2, $conflicts);
        // Clipped to each day rather than reported as one 23:00–02:00 block.
        $this->assertSame('23:00–24:00', $conflicts[0]->a->time);
        $this->assertSame('00:00–02:00', $conflicts[1]->a->time);
    }

    public function test_a_blocking_inside_working_hours_is_only_a_soft_overlap(): void
    {
        $room = Room::factory()->create();
        $this->workBlock(['room_id' => $room->id, 'start_time' => '08:00', 'end_time' => '20:00']);
        RoomBlocking::factory()->recurring()->create([
            'room_id' => $room->id,
            'day_of_week' => DayOfWeek::fromCarbon(Carbon::today()),
            'week_type' => WeekType::All,
            'start_time' => '16:00',
            'end_time' => '18:00',
        ]);

        $conflicts = ConflictFinder::upcoming(0);

        $this->assertCount(1, $conflicts);
        $this->assertSame(ConflictSeverity::Soft, $conflicts[0]->severity);
        $this->assertFalse($conflicts[0]->isHard());
        $this->assertSame('Blokace uvnitř pracovní doby', $conflicts[0]->title);
    }

    public function test_repeating_soft_overlaps_collapse_into_one(): void
    {
        $room = Room::factory()->create();
        $therapist = StaffProfile::factory()->create();
        RoomBlocking::factory()->recurring()->create([
            'room_id' => $room->id,
            'day_of_week' => DayOfWeek::fromCarbon(Carbon::today()),
            'week_type' => WeekType::All,
            'start_time' => '16:00',
            'end_time' => '18:00',
        ]);

        // The same weekday three weeks running: three rows, one pattern.
        foreach ([0, 7, 14] as $offset) {
            $this->workBlock([
                'therapist_id' => $therapist->id,
                'room_id' => $room->id,
                'work_date' => Carbon::today()->addDays($offset)->toDateString(),
                'start_time' => '08:00',
                'end_time' => '20:00',
            ]);
        }

        $conflicts = ConflictFinder::upcoming(20);
        $this->assertCount(3, $conflicts);

        $collapsed = ConflictFinder::collapseRecurring($conflicts);

        $this->assertCount(1, $collapsed);
        $this->assertSame(3, $collapsed[0]->occurrences);
        $this->assertSame(Carbon::today()->toDateString(), $collapsed[0]->date);
    }

    // --- Pairings we deliberately do not report --------------------------------

    public function test_a_reservation_inside_its_own_working_hours_is_not_a_conflict(): void
    {
        $therapist = StaffProfile::factory()->create();
        $room = Room::factory()->create();
        $this->workBlock(['therapist_id' => $therapist->id, 'room_id' => $room->id, 'start_time' => '08:00', 'end_time' => '16:00']);
        $this->reservation(['therapist_id' => $therapist->id, 'room_id' => $room->id, 'start_time' => '10:00', 'end_time' => '11:00']);

        $this->assertSame([], ConflictFinder::upcoming());
    }

    public function test_two_therapists_sharing_a_room_in_working_hours_is_not_a_conflict(): void
    {
        $room = Room::factory()->create();
        $this->workBlock(['room_id' => $room->id, 'start_time' => '08:00', 'end_time' => '16:00']);
        $this->workBlock(['room_id' => $room->id, 'start_time' => '09:00', 'end_time' => '12:00']);

        $this->assertSame([], ConflictFinder::upcoming());
    }

    // --- forReservation --------------------------------------------------------

    public function test_for_reservation_returns_the_room_clash(): void
    {
        $room = Room::factory()->create();
        $a = $this->reservation(['room_id' => $room->id, 'start_time' => '09:00', 'end_time' => '10:00']);
        $b = $this->reservation(['room_id' => $room->id, 'start_time' => '09:30', 'end_time' => '10:30']);

        $conflicts = ConflictFinder::forReservation($a);

        $this->assertCount(1, $conflicts);
        $this->assertSame('room', $conflicts[0]->type);
        $this->assertTrue($conflicts[0]->a->matches('reservation', (string) $a->getKey()));
        $this->assertTrue($conflicts[0]->b->matches('reservation', (string) $b->getKey()));
    }

    public function test_for_reservation_puts_the_reservation_first_even_when_it_starts_later(): void
    {
        $room = Room::factory()->create();
        $this->reservation(['room_id' => $room->id, 'start_time' => '09:00', 'end_time' => '11:00']);
        $later = $this->reservation(['room_id' => $room->id, 'start_time' => '10:00', 'end_time' => '10:30']);

        $conflicts = ConflictFinder::forReservation($later);

        $this->assertCount(1, $conflicts);
        $this->assertTrue($conflicts[0]->a->matches('reservation', (string) $later->getKey()));
    }

    public function test_for_reservation_returns_the_therapist_clash(): void
    {
        $therapist = StaffProfile::factory()->create();
        $a = $this->reservation(['therapist_id' => $therapist->id, 'room_id' => Room::factory()->create()->id, 'start_time' => '09:00', 'end_time' => '10:00']);
        $b = $this->reservation(['therapist_id' => $therapist->id, 'room_id' => Room::factory()->create()->id, 'start_time' => '09:30', 'end_time' => '10:30']);

        $conflicts = ConflictFinder::forReservation($a);

        $this->assertCount(1, $conflicts);
        $this->assertSame('therapist', $conflicts[0]->type);
        $this->assertTrue($conflicts[0]->b->matches('reservation', (string) $b->getKey()));
    }

    public function test_for_reservation_reports_a_clashing_lesson(): void
    {
        $room = Room::factory()->create();
        $reservation = $this->reservation(['room_id' => $room->id, 'start_time' => '10:00', 'end_time' => '11:00']);
        $lesson = $this->lesson(['room_id' => $room->id, 'start_time' => '10:30', 'end_time' => '12:00']);

        $conflicts = ConflictFinder::forReservation($reservation);

        $this->assertCount(1, $conflicts);
        $this->assertTrue($conflicts[0]->a->matches('reservation', (string) $reservation->getKey()));
        $this->assertTrue($conflicts[0]->b->matches('lesson', (string) $lesson->getKey()));
    }

    public function test_for_reservation_is_empty_when_no_overlap_or_touching(): void
    {
        $room = Room::factory()->create();
        $a = $this->reservation(['room_id' => $room->id, 'start_time' => '09:00', 'end_time' => '10:00']);
        $this->reservation(['room_id' => $room->id, 'start_time' => '10:00', 'end_time' => '11:00']); // touching

        $this->assertSame([], ConflictFinder::forReservation($a));
    }

    public function test_for_reservation_excludes_self_and_cancelled(): void
    {
        $room = Room::factory()->create();
        $a = $this->reservation(['room_id' => $room->id, 'start_time' => '09:00', 'end_time' => '10:00']);
        $this->reservation(['room_id' => $room->id, 'start_time' => '09:30', 'end_time' => '10:30', 'status' => ReservationStatus::Cancelled]);

        $this->assertSame([], ConflictFinder::forReservation($a));
    }

    public function test_imported_visits_are_excluded_from_conflicts(): void
    {
        // Historical imports share a placeholder time (all 08:00) in one room —
        // they must not register as conflicts anywhere.
        $room = Room::factory()->create();
        $a = $this->reservation(['room_id' => $room->id, 'start_time' => '08:00', 'end_time' => '08:30', 'imported_at' => Carbon::now()]);
        $b = $this->reservation(['room_id' => $room->id, 'start_time' => '08:00', 'end_time' => '08:30', 'imported_at' => Carbon::now()]);

        $this->assertSame([], ConflictFinder::upcoming());
        $this->assertSame([], ConflictFinder::forReservation($a));
        $this->assertSame([], ConflictFinder::forReservation($b));
    }

    public function test_for_reservation_does_not_flag_past_reservations(): void
    {
        // Conflict state is rolling: a clash on a past date is never warned about,
        // even for non-imported rows (historical data we keep but don't police).
        $room = Room::factory()->create();
        $yesterday = Carbon::yesterday()->toDateString();
        $a = $this->reservation(['room_id' => $room->id, 'reservation_date' => $yesterday, 'start_time' => '09:00', 'end_time' => '10:00']);
        $this->reservation(['room_id' => $room->id, 'reservation_date' => $yesterday, 'start_time' => '09:30', 'end_time' => '10:30']);

        $this->assertSame([], ConflictFinder::forReservation($a));
    }
}
