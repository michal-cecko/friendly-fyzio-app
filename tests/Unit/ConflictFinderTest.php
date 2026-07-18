<?php

namespace Tests\Unit;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\TherapistProfile;
use App\Support\Reservations\ConflictFinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ConflictFinderTest extends TestCase
{
    use RefreshDatabase;

    private function reservation(array $attributes): Reservation
    {
        return Reservation::factory()->create([
            'reservation_date' => Carbon::today()->toDateString(),
            'status' => ReservationStatus::Confirmed,
            ...$attributes,
        ]);
    }

    public function test_overlapping_reservations_in_the_same_room_conflict(): void
    {
        $room = Room::factory()->create();
        $this->reservation(['room_id' => $room->id, 'start_time' => '09:00', 'end_time' => '10:00']);
        $this->reservation(['room_id' => $room->id, 'start_time' => '09:30', 'end_time' => '10:30']);

        $conflicts = ConflictFinder::upcoming();

        $this->assertCount(1, array_filter($conflicts, fn ($c): bool => $c['type'] === 'room'));
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

        $roomConflicts = array_filter(ConflictFinder::upcoming(), fn ($c): bool => $c['type'] === 'room');

        $this->assertCount(0, $roomConflicts);
    }

    public function test_same_therapist_overlap_is_a_therapist_conflict(): void
    {
        $therapist = TherapistProfile::factory()->create();
        // Different rooms so only the therapist dimension can conflict.
        $this->reservation(['therapist_id' => $therapist->id, 'room_id' => Room::factory()->create()->id, 'start_time' => '09:00', 'end_time' => '10:00']);
        $this->reservation(['therapist_id' => $therapist->id, 'room_id' => Room::factory()->create()->id, 'start_time' => '09:30', 'end_time' => '10:30']);

        $conflicts = ConflictFinder::upcoming();

        $this->assertCount(1, array_filter($conflicts, fn ($c): bool => $c['type'] === 'therapist'));
        $this->assertCount(0, array_filter($conflicts, fn ($c): bool => $c['type'] === 'room'));
    }

    public function test_a_long_reservation_overlapping_two_later_ones_yields_two_conflicts(): void
    {
        $room = Room::factory()->create();
        $this->reservation(['room_id' => $room->id, 'start_time' => '09:00', 'end_time' => '12:00']);
        $this->reservation(['room_id' => $room->id, 'start_time' => '10:00', 'end_time' => '10:30']);
        $this->reservation(['room_id' => $room->id, 'start_time' => '11:00', 'end_time' => '11:30']);

        $roomConflicts = array_filter(ConflictFinder::upcoming(), fn ($c): bool => $c['type'] === 'room');

        $this->assertCount(2, $roomConflicts);
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

    public function test_for_reservation_returns_the_room_clash(): void
    {
        $room = Room::factory()->create();
        $a = $this->reservation(['room_id' => $room->id, 'start_time' => '09:00', 'end_time' => '10:00']);
        $b = $this->reservation(['room_id' => $room->id, 'start_time' => '09:30', 'end_time' => '10:30']);

        $conflicts = ConflictFinder::forReservation($a);

        $this->assertCount(1, $conflicts);
        $this->assertSame('room', $conflicts[0]['type']);
        $this->assertTrue($conflicts[0]['other']->is($b));
    }

    public function test_for_reservation_returns_the_therapist_clash(): void
    {
        $therapist = TherapistProfile::factory()->create();
        $a = $this->reservation(['therapist_id' => $therapist->id, 'room_id' => Room::factory()->create()->id, 'start_time' => '09:00', 'end_time' => '10:00']);
        $b = $this->reservation(['therapist_id' => $therapist->id, 'room_id' => Room::factory()->create()->id, 'start_time' => '09:30', 'end_time' => '10:30']);

        $conflicts = ConflictFinder::forReservation($a);

        $this->assertCount(1, $conflicts);
        $this->assertSame('therapist', $conflicts[0]['type']);
        $this->assertTrue($conflicts[0]['other']->is($b));
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
}
