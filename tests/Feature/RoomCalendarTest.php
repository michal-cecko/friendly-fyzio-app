<?php

namespace Tests\Feature;

use App\Enums\DayOfWeek;
use App\Enums\ReservationStatus;
use App\Enums\WeekType;
use App\Filament\Resources\Rooms\RoomResource;
use App\Filament\Widgets\ReservationCalendar;
use App\Models\Building;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomBlocking;
use App\Models\Service;
use App\Models\TherapistProfile;
use App\Models\TherapistWeeklySchedule;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class RoomCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected Carbon $monday;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->monday = Carbon::parse('2026-01-05')->startOfWeek(Carbon::MONDAY);
    }

    protected function makeRoom(string $name = 'Sál 1'): Room
    {
        $building = Building::create(['name' => 'Budova', 'address' => 'Adresa']);

        return Room::create(['building_id' => $building->getKey(), 'name' => $name]);
    }

    protected function makeTherapist(): TherapistProfile
    {
        return TherapistProfile::create(['user_id' => User::factory()->therapist()->create()->getKey()]);
    }

    /**
     * @return array<int, mixed>
     */
    protected function fetchWeek(ReservationCalendar $calendar): array
    {
        return $calendar->fetchEvents([
            'start' => $this->monday->toDateString(),
            'end' => $this->monday->copy()->addDays(7)->toDateString(),
            'timezone' => config('app.timezone'),
        ]);
    }

    protected function reservationIn(Room $room, TherapistProfile $therapist): Reservation
    {
        return Reservation::factory()->create([
            'therapist_id' => $therapist->getKey(),
            'room_id' => $room->getKey(),
            'reservation_date' => $this->monday->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => ReservationStatus::Confirmed,
        ]);
    }

    public function test_reservations_mode_is_limited_to_the_scoped_room(): void
    {
        $roomA = $this->makeRoom('Sál A');
        $roomB = $this->makeRoom('Sál B');
        $therapist = $this->makeTherapist();

        $inA = $this->reservationIn($roomA, $therapist);
        $inB = $this->reservationIn($roomB, $therapist);

        $blockA = RoomBlocking::create([
            'room_id' => $roomA->getKey(),
            'is_recurring' => false,
            'start_at' => $this->monday->copy()->setTime(15, 0),
            'end_at' => $this->monday->copy()->setTime(16, 0),
            'reason' => 'Servis A',
        ]);
        $blockB = RoomBlocking::create([
            'room_id' => $roomB->getKey(),
            'is_recurring' => false,
            'start_at' => $this->monday->copy()->setTime(15, 0),
            'end_at' => $this->monday->copy()->setTime(16, 0),
            'reason' => 'Servis B',
        ]);

        $calendar = new ReservationCalendar;
        $calendar->room = $roomA;
        $ids = array_column($this->fetchWeek($calendar), 'id');

        $this->assertContains($inA->getKey(), $ids);
        $this->assertNotContains($inB->getKey(), $ids);
        $this->assertContains('blocking:'.$blockA->getKey(), $ids);
        $this->assertNotContains('blocking:'.$blockB->getKey(), $ids);
    }

    public function test_template_mode_is_limited_to_the_scoped_room(): void
    {
        $roomA = $this->makeRoom('Sál A');
        $roomB = $this->makeRoom('Sál B');
        $therapist = $this->makeTherapist();

        $base = ['day_of_week' => DayOfWeek::Monday, 'week_type' => WeekType::All, 'start_time' => '09:00', 'end_time' => '10:00'];
        $schedA = TherapistWeeklySchedule::factory()->for($therapist, 'therapist')->create([...$base, 'room_id' => $roomA->getKey()]);
        $schedB = TherapistWeeklySchedule::factory()->for($therapist, 'therapist')->create([...$base, 'room_id' => $roomB->getKey()]);

        $blockA = RoomBlocking::create([...$base, 'room_id' => $roomA->getKey(), 'is_recurring' => true, 'reason' => 'Porada A']);
        $blockB = RoomBlocking::create([...$base, 'room_id' => $roomB->getKey(), 'is_recurring' => true, 'reason' => 'Porada B']);

        $calendar = new ReservationCalendar;
        $calendar->room = $roomA;
        $calendar->mode = 'template';
        $ids = array_column($this->fetchWeek($calendar), 'id');

        $this->assertContains('schedule:'.$schedA->getKey(), $ids);
        $this->assertNotContains('schedule:'.$schedB->getKey(), $ids);
        $this->assertContains('blocking:'.$blockA->getKey(), $ids);
        $this->assertNotContains('blocking:'.$blockB->getKey(), $ids);
    }

    public function test_day_summary_is_scoped_to_the_room(): void
    {
        $roomA = $this->makeRoom('Sál A');
        $roomB = $this->makeRoom('Sál B');
        $therapist = $this->makeTherapist();
        $service = Service::factory()->create();
        $client = User::factory()->customer()->create();

        // Room A: 4h available (08–12).
        TherapistWeeklySchedule::factory()->for($therapist, 'therapist')->create([
            'room_id' => $roomA->getKey(), 'day_of_week' => DayOfWeek::Monday, 'week_type' => WeekType::All, 'start_time' => '08:00', 'end_time' => '12:00',
        ]);
        // Room B: extra availability that must NOT leak into room A's summary.
        TherapistWeeklySchedule::factory()->for($therapist, 'therapist')->create([
            'room_id' => $roomB->getKey(), 'day_of_week' => DayOfWeek::Monday, 'week_type' => WeekType::All, 'start_time' => '08:00', 'end_time' => '16:00',
        ]);

        $common = [
            'client_id' => $client->getKey(),
            'service_id' => $service->getKey(),
            'therapist_id' => $therapist->getKey(),
            'reservation_date' => $this->monday->toDateString(),
            'status' => ReservationStatus::Confirmed,
        ];
        Reservation::factory()->create([...$common, 'room_id' => $roomA->getKey(), 'start_time' => '09:00', 'end_time' => '10:00']);
        // A booking in room B must not count toward room A's day summary.
        Reservation::factory()->create([...$common, 'room_id' => $roomB->getKey(), 'start_time' => '09:00', 'end_time' => '11:00']);

        $calendar = new ReservationCalendar;
        $calendar->room = $roomA;
        $calendar->calendarDate = $this->monday->toDateString();

        $summary = $calendar->daySummary();

        $this->assertSame(1, $summary['count']);          // only room A's reservation
        $this->assertSame('1,0', $summary['hours']);      // 60 min booked in room A
        $this->assertSame('3h', $summary['free']);        // 240 available − 60 booked
        $this->assertSame(25, $summary['utilization']);   // 60 / 240
    }

    public function test_template_room_picker_is_hidden_when_scoped(): void
    {
        $room = $this->makeRoom();
        $this->actingAs(User::factory()->admin()->create());

        // The global calendar offers a room picker in template mode …
        Livewire::test(ReservationCalendar::class)
            ->set('mode', 'template')
            ->assertSeeHtml('wire:model.live="templateRoomId"');

        // … but a room-scoped calendar hides it.
        Livewire::test(ReservationCalendar::class, ['room' => $room])
            ->set('mode', 'template')
            ->assertDontSeeHtml('wire:model.live="templateRoomId"');
    }

    public function test_one_time_blocking_added_from_room_calendar_attaches_to_room(): void
    {
        $room = $this->makeRoom();
        $this->actingAs(User::factory()->admin()->create());

        $start = $this->monday->copy()->setTime(15, 0);

        // No room_id supplied — the scoped calendar locks and forces it.
        Livewire::test(ReservationCalendar::class, ['room' => $room])
            ->callAction('addOneTimeBlocking', [
                'start_at' => $start->toDateTimeString(),
                'end_at' => $start->copy()->addHour()->toDateTimeString(),
                'reason' => 'Servis',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('room_blockings', [
            'room_id' => $room->getKey(),
            'is_recurring' => false,
            'reason' => 'Servis',
        ]);
    }

    public function test_working_hours_added_from_room_calendar_attaches_to_room(): void
    {
        $room = $this->makeRoom();
        $therapist = $this->makeTherapist();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ReservationCalendar::class, ['room' => $room])
            ->set('mode', 'template')
            ->callAction('addWorkingHours', [
                'therapist_id' => $therapist->getKey(),
                'day_of_week' => DayOfWeek::Monday->value,
                'week_type' => WeekType::All->value,
                'start_time' => '08:00',
                'end_time' => '14:00',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('therapist_weekly_schedules', [
            'therapist_id' => $therapist->getKey(),
            'room_id' => $room->getKey(),
            'day_of_week' => 'monday',
            'start_time' => '08:00',
        ]);
    }

    public function test_view_page_shows_room_details_without_relation_tables(): void
    {
        $room = $this->makeRoom('Sál Zeta');
        $this->actingAs(User::factory()->admin()->create());

        // Details infolist renders; the calendar replaces the schedule/blocking tables.
        $this->get(RoomResource::getUrl('view', ['record' => $room]))
            ->assertSuccessful()
            ->assertSee('Sál Zeta')
            ->assertDontSee('Rozvrh terapeutů');
    }

    public function test_edit_page_is_form_only(): void
    {
        $room = $this->makeRoom();
        $this->actingAs(User::factory()->admin()->create());

        $this->get(RoomResource::getUrl('edit', ['record' => $room]))
            ->assertSuccessful()
            ->assertDontSee('Rozvrh terapeutů');
    }
}
