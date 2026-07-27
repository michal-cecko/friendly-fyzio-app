<?php

namespace Tests\Feature\Reservations;

use App\Filament\Clusters\Provoz\Resources\Reservations\Pages\ListReservations;
use App\Models\Building;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The break a visit was booked with is frozen onto the reservation the moment it
 * is saved, so the schedule keeps showing the gap the client was actually given
 * even after the therapist changes how long they rest.
 */
class ReservationBreakTest extends TestCase
{
    use RefreshDatabase;

    private Service $service;

    private StaffProfile $therapist;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $building = Building::create(['name' => 'Budova', 'address' => 'Adresa']);
        $this->room = Room::create(['building_id' => $building->getKey(), 'name' => 'Sál']);
        $this->service = Service::factory()->create();
        $this->therapist = StaffProfile::create([
            'user_id' => User::factory()->therapist()->create()->getKey(),
            'break_blocks' => 1,
        ]);
        $this->service->therapists()->attach($this->therapist);
    }

    private function book(array $overrides = []): Reservation
    {
        return Reservation::factory()->create([
            'client_id' => User::factory()->customer()->create()->getKey(),
            'service_id' => $this->service->getKey(),
            'therapist_id' => $this->therapist->getKey(),
            'room_id' => $this->room->getKey(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            ...$overrides,
        ]);
    }

    public function test_a_new_reservation_inherits_the_therapists_default_break(): void
    {
        $this->assertSame(15, $this->book()->break_minutes);
    }

    public function test_a_service_override_wins_over_the_default(): void
    {
        $this->service->therapists()->updateExistingPivot($this->therapist->getKey(), ['break_blocks' => 2]);

        $this->assertSame(30, $this->book()->break_minutes);
    }

    public function test_an_override_of_no_break_at_all_is_honoured(): void
    {
        // Zero is a real answer, not "unset" — it must not fall through to the
        // therapist's default.
        $this->service->therapists()->updateExistingPivot($this->therapist->getKey(), ['break_blocks' => 0]);

        $this->assertSame(0, $this->book()->break_minutes);
    }

    public function test_moving_a_visit_to_another_therapist_recomputes_the_break(): void
    {
        $reservation = $this->book();
        $colleague = StaffProfile::create([
            'user_id' => User::factory()->therapist()->create()->getKey(),
            'break_blocks' => 3,
        ]);

        $reservation->update(['therapist_id' => $colleague->getKey()]);

        $this->assertSame(45, $reservation->fresh()->break_minutes);
    }

    public function test_an_unrelated_edit_leaves_the_frozen_break_alone(): void
    {
        $reservation = $this->book();
        $this->therapist->update(['break_blocks' => 4]);

        $reservation->update(['notes' => 'Přišla o deset minut dřív.']);

        $this->assertSame(15, $reservation->fresh()->break_minutes);
    }

    public function test_a_caller_that_sets_the_break_itself_is_taken_at_its_word(): void
    {
        // An import replaying history knows better than the current profile.
        $this->assertSame(5, $this->book(['break_minutes' => 5])->break_minutes);
    }

    public function test_the_list_shows_when_the_therapist_is_free_again(): void
    {
        $reservation = $this->book();

        $this->actingAs(User::factory()->admin()->create());

        // „Do" is the visit's end plus the break — 10:00 + 15 min — with the
        // break itself spelled out underneath.
        Livewire::test(ListReservations::class)
            ->assertCanSeeTableRecords([$reservation])
            ->assertTableColumnStateSet('end_time', '10:15', $reservation)
            ->assertSee('vč. 15 min pauzy');
    }

    public function test_a_breakless_visit_ends_when_it_ends(): void
    {
        $this->service->therapists()->updateExistingPivot($this->therapist->getKey(), ['break_blocks' => 0]);
        $reservation = $this->book();

        $this->assertSame('10:00', $reservation->endsAtIncludingBreak()->format('H:i'));
        $this->assertNull($reservation->breakLabel());
    }
}
