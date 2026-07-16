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
use App\Models\TherapistWorkBlock;
use App\Models\TherapistWorkBlockSeries;
use App\Models\User;
use App\Support\WorkBlocks\WorkBlockGenerator;
use Filament\Actions\Testing\TestAction;
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

    protected function makeWorkBlock(TherapistProfile $therapist, Room $room, array $attributes = []): TherapistWorkBlock
    {
        return TherapistWorkBlock::factory()->for($therapist, 'therapist')->create([
            'room_id' => $room->getKey(),
            'work_date' => $this->monday->toDateString(),
            'start_time' => '09:00',
            'end_time' => '12:00',
            ...$attributes,
        ]);
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

    public function test_template_mode_returns_dated_work_blocks_and_blockings(): void
    {
        $room = $this->makeRoom();
        $therapist = $this->makeTherapist();

        $inWeek = $this->makeWorkBlock($therapist, $room);
        $outsideWeek = $this->makeWorkBlock($therapist, $room, [
            'work_date' => $this->monday->copy()->addWeek()->toDateString(),
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

        $events = $this->fetchTemplateWeek(new ReservationCalendar);
        $ids = array_column($events, 'id');

        $this->assertContains('schedule:'.$inWeek->getKey(), $ids);
        $this->assertNotContains('schedule:'.$outsideWeek->getKey(), $ids);
        $this->assertContains('blocking:'.$recurring->getKey(), $ids);
        $this->assertContains('blocking:'.$oneTime->getKey(), $ids);

        // The work block renders on its real date.
        $blockEvent = collect($events)->firstWhere('id', 'schedule:'.$inWeek->getKey());
        $this->assertStringStartsWith($this->monday->toDateString(), $blockEvent['start']);
    }

    public function test_template_mode_expands_recurring_blocking_by_week_parity(): void
    {
        $room = $this->makeRoom();

        // 2026-01-05 lies in ISO week 2 (even); an odd-week blocking must not render.
        $this->assertSame(WeekType::Even, WeekType::forDate($this->monday));

        $oddBlocking = RoomBlocking::create([
            'room_id' => $room->getKey(),
            'is_recurring' => true,
            'day_of_week' => DayOfWeek::Monday,
            'week_type' => WeekType::Odd,
            'start_time' => '13:00',
            'end_time' => '14:00',
        ]);

        $evenBlocking = RoomBlocking::create([
            'room_id' => $room->getKey(),
            'is_recurring' => true,
            'day_of_week' => DayOfWeek::Monday,
            'week_type' => WeekType::Even,
            'start_time' => '14:00',
            'end_time' => '15:00',
        ]);

        $ids = array_column($this->fetchTemplateWeek(new ReservationCalendar), 'id');

        $this->assertNotContains('blocking:'.$oddBlocking->getKey(), $ids);
        $this->assertContains('blocking:'.$evenBlocking->getKey(), $ids);
    }

    public function test_template_room_filter_limits_events(): void
    {
        $roomA = $this->makeRoom('Sál A');
        $roomB = $this->makeRoom('Sál B');
        $therapist = $this->makeTherapist();

        $inA = $this->makeWorkBlock($therapist, $roomA);
        $inB = $this->makeWorkBlock($therapist, $roomB, ['start_time' => '13:00', 'end_time' => '15:00']);

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

        $this->makeWorkBlock($therapist, $room, ['start_time' => '08:00', 'end_time' => '12:00']);

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

    public function test_can_add_single_work_block_from_template_toolbar(): void
    {
        $room = $this->makeRoom();
        $therapist = $this->makeTherapist();
        $this->actingAs(User::factory()->admin()->create());

        $date = Carbon::today()->addWeek()->startOfWeek(Carbon::MONDAY);

        Livewire::test(ReservationCalendar::class)
            ->set('mode', 'template')
            ->callAction('addWorkingHours', [
                'therapist_id' => $therapist->getKey(),
                'room_id' => $room->getKey(),
                'work_date' => $date->toDateString(),
                'start_time' => '08:00',
                'end_time' => '14:00',
                'repeat' => 'none',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('therapist_work_blocks', [
            'therapist_id' => $therapist->getKey(),
            'work_date' => $date->toDateString(),
            'start_time' => '08:00:00',
            'series_id' => null,
        ]);
    }

    public function test_adding_repeating_work_block_materializes_the_series(): void
    {
        $room = $this->makeRoom();
        $therapist = $this->makeTherapist();
        $this->actingAs(User::factory()->admin()->create());

        $date = Carbon::today()->addWeek()->startOfWeek(Carbon::MONDAY);

        Livewire::test(ReservationCalendar::class)
            ->set('mode', 'template')
            ->callAction('addWorkingHours', [
                'therapist_id' => $therapist->getKey(),
                'room_id' => $room->getKey(),
                'work_date' => $date->toDateString(),
                'start_time' => '08:00',
                'end_time' => '14:00',
                'repeat' => 'weekly',
                'repeat_until' => $date->copy()->addWeeks(2)->toDateString(),
            ])
            ->assertHasNoActionErrors();

        $series = TherapistWorkBlockSeries::sole();
        $this->assertSame(WeekType::All, $series->week_type);
        $this->assertSame(DayOfWeek::Monday, $series->day_of_week);
        $this->assertSame($date->copy()->addWeeks(2)->toDateString(), $series->ends_on->toDateString());

        $this->assertSame(
            [
                $date->toDateString(),
                $date->copy()->addWeek()->toDateString(),
                $date->copy()->addWeeks(2)->toDateString(),
            ],
            $series->blocks()->orderBy('work_date')->get()
                ->map(fn (TherapistWorkBlock $block): string => $block->work_date->toDateString())->all(),
        );
    }

    public function test_adding_overlapping_single_work_block_is_rejected(): void
    {
        $room = $this->makeRoom();
        $therapist = $this->makeTherapist();
        $this->actingAs(User::factory()->admin()->create());

        $date = Carbon::today()->addWeek()->startOfWeek(Carbon::MONDAY);

        $this->makeWorkBlock($therapist, $room, [
            'work_date' => $date->toDateString(),
            'start_time' => '08:00',
            'end_time' => '12:00',
        ]);

        Livewire::test(ReservationCalendar::class)
            ->set('mode', 'template')
            ->callAction('addWorkingHours', [
                'therapist_id' => $therapist->getKey(),
                'room_id' => $room->getKey(),
                'work_date' => $date->toDateString(),
                'start_time' => '10:00',
                'end_time' => '14:00',
                'repeat' => 'none',
            ])
            ->assertHasActionErrors(['end_time']);

        $this->assertSame(1, TherapistWorkBlock::count());
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

    public function test_template_mode_renders_sidebar_summary_too(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ReservationCalendar::class)
            ->set('mode', 'template')
            ->assertSuccessful()
            ->assertSee('TERMÍNY')
            ->assertSee('VYTÍŽENOST')
            ->assertSee('Vytíženost dne');
    }

    public function test_clicking_template_work_block_marks_it_for_editing(): void
    {
        $room = $this->makeRoom();
        $therapist = $this->makeTherapist();
        $this->actingAs(User::factory()->admin()->create());

        $block = $this->makeWorkBlock($therapist, $room);

        Livewire::test(ReservationCalendar::class)
            ->set('mode', 'template')
            ->call('onEventClick', ['id' => 'schedule:'.$block->getKey()])
            ->assertSet('editingTemplateId', (string) $block->getKey());
    }

    public function test_template_selection_mode_toggles_selection_instead_of_editing(): void
    {
        $room = $this->makeRoom();
        $therapist = $this->makeTherapist();
        $this->actingAs(User::factory()->admin()->create());

        $block = $this->makeWorkBlock($therapist, $room);

        Livewire::test(ReservationCalendar::class)
            ->set('mode', 'template')
            ->set('selectionMode', true)
            ->call('onEventClick', ['id' => 'schedule:'.$block->getKey()])
            ->assertSet('selectedIds', ['schedule:'.$block->getKey()])
            ->assertSet('editingTemplateId', null);
    }

    public function test_template_bulk_delete_removes_selected_work_blocks_and_blockings(): void
    {
        $room = $this->makeRoom();
        $therapist = $this->makeTherapist();
        $this->actingAs(User::factory()->admin()->create());

        $block = $this->makeWorkBlock($therapist, $room);

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
            ->set('selectedIds', ['schedule:'.$block->getKey(), 'blocking:'.$blocking->getKey()])
            ->callAction('deleteSelectedTemplate')
            ->assertHasNoActionErrors();

        $this->assertDatabaseMissing('therapist_work_blocks', ['id' => $block->getKey()]);
        $this->assertDatabaseMissing('room_blockings', ['id' => $blocking->getKey()]);
    }

    public function test_delete_range_removes_only_the_therapists_blocks_in_range(): void
    {
        $room = $this->makeRoom();
        $therapist = $this->makeTherapist();
        $other = $this->makeTherapist();
        $this->actingAs(User::factory()->admin()->create());

        $inRange = $this->makeWorkBlock($therapist, $room);
        $beforeRange = $this->makeWorkBlock($therapist, $room, [
            'work_date' => $this->monday->copy()->subWeek()->toDateString(),
        ]);
        $otherTherapist = $this->makeWorkBlock($other, $room, ['start_time' => '13:00', 'end_time' => '15:00']);

        Livewire::test(ReservationCalendar::class)
            ->set('mode', 'template')
            ->callAction('deleteWorkBlocksRange', [
                'therapist_id' => $therapist->getKey(),
                'from' => $this->monday->toDateString(),
                'until' => $this->monday->copy()->addDays(6)->toDateString(),
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseMissing('therapist_work_blocks', ['id' => $inRange->getKey()]);
        $this->assertDatabaseHas('therapist_work_blocks', ['id' => $beforeRange->getKey()]);
        $this->assertDatabaseHas('therapist_work_blocks', ['id' => $otherTherapist->getKey()]);
    }

    public function test_delete_this_and_following_trims_the_series(): void
    {
        $room = $this->makeRoom();
        $therapist = $this->makeTherapist();
        $this->actingAs(User::factory()->admin()->create());

        $series = TherapistWorkBlockSeries::factory()->for($therapist, 'therapist')->create([
            'room_id' => $room->getKey(),
            'day_of_week' => DayOfWeek::Monday,
            'week_type' => WeekType::All,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'starts_on' => '2026-01-05',
            'ends_on' => null,
            'generated_until' => '2026-01-04',
        ]);
        app(WorkBlockGenerator::class)->materialize($series, Carbon::parse('2026-02-28'));

        $middle = $series->blocks()->whereDate('work_date', '2026-01-19')->sole();

        Livewire::test(ReservationCalendar::class)
            ->set('mode', 'template')
            ->set('editingTemplateId', (string) $middle->getKey())
            ->mountAction('editSchedule')
            ->callAction(TestAction::make('deleteScheduleFromHere'));

        // 2026-01-05 and 2026-01-12 remain; everything from 2026-01-19 on is gone.
        $this->assertSame(
            ['2026-01-05', '2026-01-12'],
            $series->blocks()->orderBy('work_date')->get()
                ->map(fn (TherapistWorkBlock $block): string => $block->work_date->toDateString())->all(),
        );
        $this->assertSame('2026-01-18', $series->fresh()->ends_on->toDateString());

        // The capped series is never re-extended.
        $this->artisan('work-blocks:extend')->assertSuccessful();
        $this->assertSame(2, $series->blocks()->count());
    }
}
