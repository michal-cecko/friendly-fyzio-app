<?php

namespace Tests\Feature;

use App\Enums\DayOfWeek;
use App\Enums\ReservationStatus;
use App\Enums\WeekType;
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

class CalendarModesTest extends TestCase
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
    protected function fetchTemplateWeek(ReservationCalendar $calendar): array
    {
        $calendar->mode = 'template';

        return $calendar->fetchEvents([
            'start' => $this->monday->toDateString(),
            'end' => $this->monday->copy()->addDays(7)->toDateString(),
            'timezone' => config('app.timezone'),
        ]);
    }

    /**
     * @return array<int, mixed>
     */
    protected function fetchReservationWeek(ReservationCalendar $calendar): array
    {
        return $calendar->fetchEvents([
            'start' => $this->monday->toDateString(),
            'end' => $this->monday->copy()->addDays(7)->toDateString(),
            'timezone' => config('app.timezone'),
        ]);
    }

    public function test_reservations_mode_overlays_one_time_blockings_only(): void
    {
        $room = $this->makeRoom();

        $oneTime = RoomBlocking::create([
            'room_id' => $room->getKey(),
            'is_recurring' => false,
            'start_at' => $this->monday->copy()->setTime(15, 0),
            'end_at' => $this->monday->copy()->setTime(16, 0),
            'reason' => 'Servis',
        ]);

        $recurring = RoomBlocking::create([
            'room_id' => $room->getKey(),
            'is_recurring' => true,
            'day_of_week' => DayOfWeek::Monday,
            'week_type' => WeekType::All,
            'start_time' => '13:00',
            'end_time' => '14:00',
        ]);

        $ids = array_column($this->fetchReservationWeek(new ReservationCalendar), 'id');

        $this->assertContains('blocking:'.$oneTime->getKey(), $ids);
        $this->assertNotContains('blocking:'.$recurring->getKey(), $ids);
    }

    public function test_clicking_one_time_blocking_marks_it_for_editing(): void
    {
        $room = $this->makeRoom();
        $this->actingAs(User::factory()->admin()->create());

        $blocking = RoomBlocking::create([
            'room_id' => $room->getKey(),
            'is_recurring' => false,
            'start_at' => $this->monday->copy()->setTime(15, 0),
            'end_at' => $this->monday->copy()->setTime(16, 0),
            'reason' => 'Servis',
        ]);

        Livewire::test(ReservationCalendar::class)
            ->call('onEventClick', ['id' => 'blocking:'.$blocking->getKey()])
            ->assertSet('editingTemplateId', (string) $blocking->getKey());
    }

    public function test_template_mode_returns_working_hours_and_recurring_blockings(): void
    {
        $room = $this->makeRoom();
        $therapist = $this->makeTherapist();

        $schedule = TherapistWeeklySchedule::factory()->for($therapist, 'therapist')->create([
            'room_id' => $room->getKey(),
            'day_of_week' => DayOfWeek::Monday,
            'week_type' => WeekType::All,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $recurring = RoomBlocking::create([
            'room_id' => $room->getKey(),
            'is_recurring' => true,
            'day_of_week' => DayOfWeek::Monday,
            'week_type' => WeekType::All,
            'start_time' => '13:00',
            'end_time' => '14:00',
            'reason' => 'Porada',
        ]);

        $oneTime = RoomBlocking::create([
            'room_id' => $room->getKey(),
            'is_recurring' => false,
            'start_at' => $this->monday->copy()->setTime(15, 0),
            'end_at' => $this->monday->copy()->setTime(16, 0),
            'reason' => 'Jednorázová',
        ]);

        $ids = array_column($this->fetchTemplateWeek(new ReservationCalendar), 'id');

        $this->assertContains('schedule:'.$schedule->getKey(), $ids);
        $this->assertContains('blocking:'.$recurring->getKey(), $ids);
        $this->assertNotContains('blocking:'.$oneTime->getKey(), $ids);
    }

    public function test_template_week_type_filter_limits_events(): void
    {
        $room = $this->makeRoom();
        $therapist = $this->makeTherapist();

        $base = ['room_id' => $room->getKey(), 'day_of_week' => DayOfWeek::Monday, 'start_time' => '09:00', 'end_time' => '10:00'];
        $odd = TherapistWeeklySchedule::factory()->for($therapist, 'therapist')->create([...$base, 'week_type' => WeekType::Odd]);
        $even = TherapistWeeklySchedule::factory()->for($therapist, 'therapist')->create([...$base, 'week_type' => WeekType::Even]);
        $all = TherapistWeeklySchedule::factory()->for($therapist, 'therapist')->create([...$base, 'week_type' => WeekType::All]);

        $calendar = new ReservationCalendar;
        $calendar->templateWeekType = 'odd';
        $ids = array_column($this->fetchTemplateWeek($calendar), 'id');

        $this->assertContains('schedule:'.$odd->getKey(), $ids);
        $this->assertContains('schedule:'.$all->getKey(), $ids);
        $this->assertNotContains('schedule:'.$even->getKey(), $ids);
    }

    public function test_template_room_filter_limits_events(): void
    {
        $roomA = $this->makeRoom('Sál A');
        $roomB = $this->makeRoom('Sál B');
        $therapist = $this->makeTherapist();

        $base = ['day_of_week' => DayOfWeek::Monday, 'week_type' => WeekType::All, 'start_time' => '09:00', 'end_time' => '10:00'];
        $inA = TherapistWeeklySchedule::factory()->for($therapist, 'therapist')->create([...$base, 'room_id' => $roomA->getKey()]);
        $inB = TherapistWeeklySchedule::factory()->for($therapist, 'therapist')->create([...$base, 'room_id' => $roomB->getKey()]);

        $calendar = new ReservationCalendar;
        $calendar->templateRoomId = $roomA->getKey();
        $ids = array_column($this->fetchTemplateWeek($calendar), 'id');

        $this->assertContains('schedule:'.$inA->getKey(), $ids);
        $this->assertNotContains('schedule:'.$inB->getKey(), $ids);
    }

    public function test_day_summary_counts_reservations_and_computes_utilization(): void
    {
        $room = $this->makeRoom();
        $therapist = $this->makeTherapist();
        $service = Service::factory()->create();
        $client = User::factory()->customer()->create();

        TherapistWeeklySchedule::factory()->for($therapist, 'therapist')->create([
            'room_id' => $room->getKey(),
            'day_of_week' => DayOfWeek::Monday,
            'week_type' => WeekType::All,
            'start_time' => '08:00',
            'end_time' => '12:00',
        ]);

        $common = [
            'client_id' => $client->getKey(),
            'service_id' => $service->getKey(),
            'therapist_id' => $therapist->getKey(),
            'room_id' => $room->getKey(),
            'reservation_date' => $this->monday->toDateString(),
        ];

        Reservation::factory()->create([...$common, 'start_time' => '09:00', 'end_time' => '10:00', 'status' => ReservationStatus::Confirmed]);
        Reservation::factory()->create([...$common, 'start_time' => '10:00', 'end_time' => '11:00', 'status' => ReservationStatus::Cancelled]);

        $calendar = new ReservationCalendar;
        $calendar->calendarDate = $this->monday->toDateString();

        $summary = $calendar->daySummary();

        $this->assertSame(1, $summary['count']);          // cancelled excluded
        $this->assertSame('1,0', $summary['hours']);      // 60 min booked
        $this->assertSame('3h', $summary['free']);        // 240 available − 60 booked
        $this->assertSame(25, $summary['utilization']);   // 60 / 240
    }

    public function test_can_add_working_hours_from_template_toolbar(): void
    {
        $room = $this->makeRoom();
        $therapist = $this->makeTherapist();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ReservationCalendar::class)
            ->set('mode', 'template')
            ->callAction('addWorkingHours', [
                'therapist_id' => $therapist->getKey(),
                'room_id' => $room->getKey(),
                'day_of_week' => DayOfWeek::Monday->value,
                'week_type' => WeekType::All->value,
                'start_time' => '08:00',
                'end_time' => '14:00',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('therapist_weekly_schedules', [
            'therapist_id' => $therapist->getKey(),
            'day_of_week' => 'monday',
            'start_time' => '08:00',
        ]);
    }

    public function test_can_add_recurring_blocking_from_template_toolbar(): void
    {
        $room = $this->makeRoom();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ReservationCalendar::class)
            ->set('mode', 'template')
            ->callAction('addBlocking', [
                'room_id' => $room->getKey(),
                'day_of_week' => DayOfWeek::Tuesday->value,
                'week_type' => WeekType::All->value,
                'start_time' => '13:00',
                'end_time' => '14:00',
                'reason' => 'Porada',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('room_blockings', [
            'room_id' => $room->getKey(),
            'is_recurring' => true,
            'day_of_week' => 'tuesday',
        ]);
    }

    public function test_can_add_one_time_blocking_from_reservations_toolbar(): void
    {
        $room = $this->makeRoom();
        $this->actingAs(User::factory()->admin()->create());

        $start = Carbon::parse('2026-01-06 15:00');

        Livewire::test(ReservationCalendar::class)
            ->callAction('addOneTimeBlocking', [
                'room_id' => $room->getKey(),
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

    public function test_reservations_mode_renders_sidebar_summary(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ReservationCalendar::class)
            ->assertSuccessful()
            ->assertSee('TERMÍNY')
            ->assertSee('VYTÍŽENOST')
            ->assertSee('Vytíženost dne');
    }

    public function test_clicking_template_schedule_marks_it_for_editing(): void
    {
        $room = $this->makeRoom();
        $therapist = $this->makeTherapist();
        $this->actingAs(User::factory()->admin()->create());

        $schedule = TherapistWeeklySchedule::factory()->for($therapist, 'therapist')->create([
            'room_id' => $room->getKey(),
            'day_of_week' => DayOfWeek::Monday,
            'week_type' => WeekType::All,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        Livewire::test(ReservationCalendar::class)
            ->set('mode', 'template')
            ->call('onEventClick', ['id' => 'schedule:'.$schedule->getKey()])
            ->assertSet('editingTemplateId', (string) $schedule->getKey());
    }

    public function test_template_selection_mode_toggles_selection_instead_of_editing(): void
    {
        $room = $this->makeRoom();
        $therapist = $this->makeTherapist();
        $this->actingAs(User::factory()->admin()->create());

        $schedule = TherapistWeeklySchedule::factory()->for($therapist, 'therapist')->create([
            'room_id' => $room->getKey(),
            'day_of_week' => DayOfWeek::Monday,
            'week_type' => WeekType::All,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        Livewire::test(ReservationCalendar::class)
            ->set('mode', 'template')
            ->set('selectionMode', true)
            ->call('onEventClick', ['id' => 'schedule:'.$schedule->getKey()])
            ->assertSet('selectedIds', ['schedule:'.$schedule->getKey()])
            ->assertSet('editingTemplateId', null);
    }

    public function test_template_bulk_delete_removes_selected_schedules_and_blockings(): void
    {
        $room = $this->makeRoom();
        $therapist = $this->makeTherapist();
        $this->actingAs(User::factory()->admin()->create());

        $schedule = TherapistWeeklySchedule::factory()->for($therapist, 'therapist')->create([
            'room_id' => $room->getKey(),
            'day_of_week' => DayOfWeek::Monday,
            'week_type' => WeekType::All,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $blocking = RoomBlocking::create([
            'room_id' => $room->getKey(),
            'is_recurring' => true,
            'day_of_week' => DayOfWeek::Monday,
            'week_type' => WeekType::All,
            'start_time' => '13:00',
            'end_time' => '14:00',
            'reason' => 'Porada',
        ]);

        Livewire::test(ReservationCalendar::class)
            ->set('mode', 'template')
            ->set('selectionMode', true)
            ->set('selectedIds', ['schedule:'.$schedule->getKey(), 'blocking:'.$blocking->getKey()])
            ->callAction('deleteSelectedTemplate')
            ->assertHasNoActionErrors();

        $this->assertDatabaseMissing('therapist_weekly_schedules', ['id' => $schedule->getKey()]);
        $this->assertDatabaseMissing('room_blockings', ['id' => $blocking->getKey()]);
    }
}
