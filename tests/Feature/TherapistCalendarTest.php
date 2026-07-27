<?php

namespace Tests\Feature;

use App\Enums\Capability;
use App\Enums\ReservationStatus;
use App\Filament\Widgets\ReservationCalendar;
use App\Models\Building;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomBlocking;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\TherapistWorkBlock;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The therapist's own calendar: the whole team's work is on the grid for
 * context, but only their own entries are theirs to open, edit or select.
 */
class TherapistCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected Carbon $monday;

    protected Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->monday = Carbon::parse('2026-01-05')->startOfWeek(Carbon::MONDAY);

        $building = Building::create(['name' => 'Budova', 'address' => 'Adresa']);
        $this->room = Room::create(['building_id' => $building->getKey(), 'name' => 'Sál 1']);
    }

    /**
     * A therapist whose auto-created staff profile is published, so they show up
     * in the calendar's therapist list.
     */
    private function therapist(): User
    {
        $user = User::factory()->therapist()->create();
        $user->staffProfile->update(['published_at' => now()]);

        return $user->refresh();
    }

    private function makeReservation(StaffProfile $therapist, string $startTime = '09:00'): Reservation
    {
        return Reservation::factory()->create([
            'client_id' => User::factory()->customer()->create()->getKey(),
            'service_id' => Service::factory()->create()->getKey(),
            'therapist_id' => $therapist->getKey(),
            'room_id' => $this->room->getKey(),
            'status' => ReservationStatus::Confirmed,
            'reservation_date' => $this->monday->toDateString(),
            'start_time' => $startTime,
            'end_time' => Carbon::parse($startTime)->addHour()->format('H:i'),
            'break_minutes' => 0,
        ]);
    }

    /**
     * A one-off blocking — the rentals and meetings that occupy a room without
     * belonging to any therapist. Passing no creator models the ones that come
     * from an import or from an administrator.
     */
    private function makeBlocking(?User $creator, string $startTime = '15:00'): RoomBlocking
    {
        $blocking = RoomBlocking::create([
            'room_id' => $this->room->getKey(),
            'is_recurring' => false,
            'reason' => 'Kuba – pronájem',
            'start_at' => $this->monday->copy()->setTimeFromTimeString($startTime),
            'end_at' => $this->monday->copy()->setTimeFromTimeString($startTime)->addHour(),
        ]);

        // The model stamps whoever is signed in as the creator; overwrite it so
        // the test says plainly whose blocking this is.
        $blocking->forceFill(['created_by' => $creator?->getKey()])->saveQuietly();

        return $blocking->refresh();
    }

    private function makeWorkBlock(StaffProfile $therapist): TherapistWorkBlock
    {
        return TherapistWorkBlock::factory()->for($therapist, 'therapist')->create([
            'room_id' => $this->room->getKey(),
            'work_date' => $this->monday->toDateString(),
            'start_time' => '08:00',
            'end_time' => '12:00',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchWeek(ReservationCalendar $calendar): array
    {
        return $calendar->fetchEvents([
            'start' => $this->monday->toDateString(),
            'end' => $this->monday->copy()->addDays(7)->toDateString(),
            'timezone' => config('app.timezone'),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     */
    private function eventFor(array $events, string $id): array
    {
        foreach ($events as $event) {
            if ((string) ($event['id'] ?? '') === $id) {
                return $event;
            }
        }

        $this->fail("Event {$id} is not on the calendar.");
    }

    public function test_a_therapist_sees_every_colleague_in_the_therapist_list(): void
    {
        $me = $this->therapist();
        $colleague = $this->therapist();

        $this->actingAs($me);

        $ids = (new ReservationCalendar)->therapists()->pluck('id')->all();

        $this->assertContains($me->staffProfile->getKey(), $ids);
        $this->assertContains($colleague->staffProfile->getKey(), $ids);
    }

    public function test_a_therapists_calendar_is_not_pre_filtered_to_themselves(): void
    {
        $this->actingAs($this->therapist());

        Livewire::test(ReservationCalendar::class)->assertSet('therapistIds', []);
    }

    public function test_a_colleagues_visit_is_shown_but_marked_read_only(): void
    {
        $me = $this->therapist();
        $colleague = $this->therapist();

        $mine = $this->makeReservation($me->staffProfile);
        $theirs = $this->makeReservation($colleague->staffProfile, '13:00');

        $this->actingAs($me);

        $events = $this->fetchWeek(new ReservationCalendar);

        $this->assertFalse($this->eventFor($events, $mine->getKey())['extendedProps']['isForeign']);
        $this->assertTrue($this->eventFor($events, $theirs->getKey())['extendedProps']['isForeign']);
    }

    public function test_an_admin_owns_the_whole_calendar(): void
    {
        $colleague = $this->therapist();
        $theirs = $this->makeReservation($colleague->staffProfile);

        $this->actingAs(User::factory()->admin()->create());

        $events = $this->fetchWeek(new ReservationCalendar);

        $this->assertFalse($this->eventFor($events, $theirs->getKey())['extendedProps']['isForeign']);
    }

    public function test_a_therapist_cannot_open_a_colleagues_visit(): void
    {
        $me = $this->therapist();
        $theirs = $this->makeReservation($this->therapist()->staffProfile);

        $this->actingAs($me);

        $component = Livewire::test(ReservationCalendar::class)
            ->call('onEventClick', ['id' => (string) $theirs->getKey()])
            ->assertActionNotMounted('edit');

        $this->assertSame([], $component->instance()->mountedActions);
    }

    public function test_a_therapist_can_open_their_own_visit(): void
    {
        $me = $this->therapist();
        $mine = $this->makeReservation($me->staffProfile);

        $this->actingAs($me);

        Livewire::test(ReservationCalendar::class)
            ->call('onEventClick', ['id' => (string) $mine->getKey()])
            ->assertActionMounted('edit');
    }

    public function test_a_therapist_cannot_bulk_select_a_colleagues_visit(): void
    {
        $me = $this->therapist();
        $mine = $this->makeReservation($me->staffProfile);
        $theirs = $this->makeReservation($this->therapist()->staffProfile, '13:00');

        $this->actingAs($me);

        Livewire::test(ReservationCalendar::class)
            ->set('selectionMode', true)
            ->call('onEventClick', ['id' => (string) $theirs->getKey()])
            ->assertSet('selectedIds', [])
            ->call('onEventClick', ['id' => (string) $mine->getKey()])
            ->assertSet('selectedIds', [(string) $mine->getKey()]);
    }

    public function test_a_therapist_cannot_open_a_colleagues_working_hours(): void
    {
        $me = $this->therapist();
        $mine = $this->makeWorkBlock($me->staffProfile);
        $theirs = $this->makeWorkBlock($this->therapist()->staffProfile);

        $this->actingAs($me);

        $component = Livewire::test(ReservationCalendar::class)
            ->set('mode', 'template')
            ->call('onEventClick', ['id' => 'schedule:'.$theirs->getKey()])
            ->assertSet('editingTemplateId', null);

        $component
            ->call('onEventClick', ['id' => 'schedule:'.$mine->getKey()])
            ->assertSet('editingTemplateId', (string) $mine->getKey());
    }

    public function test_a_colleagues_work_block_is_marked_read_only(): void
    {
        $me = $this->therapist();
        $theirs = $this->makeWorkBlock($this->therapist()->staffProfile);

        $this->actingAs($me);

        $calendar = new ReservationCalendar;
        $calendar->mode = 'template';

        $event = $this->eventFor($this->fetchWeek($calendar), 'schedule:'.$theirs->getKey());

        $this->assertTrue($event['extendedProps']['isForeign']);
    }

    public function test_the_week_count_and_day_summary_stay_about_the_viewers_own_work(): void
    {
        $me = $this->therapist();
        $this->makeReservation($me->staffProfile);
        $this->makeReservation($this->therapist()->staffProfile, '13:00');

        $this->actingAs($me);

        $calendar = new ReservationCalendar;
        $calendar->calendarDate = $this->monday->toDateString();
        $events = $this->fetchWeek($calendar);

        // Both visits are on the grid, but the counters are the viewer's own.
        $this->assertCount(2, $events);
        $this->assertSame(1, $calendar->weekCount);
        $this->assertSame(1, $calendar->daySummary()['count']);
    }

    public function test_a_therapist_only_deletes_their_own_working_hours_in_a_range(): void
    {
        $me = $this->therapist();
        $mine = $this->makeWorkBlock($me->staffProfile);
        $theirs = $this->makeWorkBlock($this->therapist()->staffProfile);

        $this->actingAs($me);

        $range = [
            'from' => $this->monday->toDateString(),
            'until' => $this->monday->copy()->addDays(6)->toDateString(),
        ];

        // A colleague is not on the picker at all, so asking for one is invalid.
        Livewire::test(ReservationCalendar::class)
            ->set('mode', 'template')
            ->callAction('deleteWorkBlocksRange', [...$range, 'therapist_id' => $theirs->therapist_id])
            ->assertHasActionErrors(['therapist_id']);

        Livewire::test(ReservationCalendar::class)
            ->set('mode', 'template')
            ->callAction('deleteWorkBlocksRange', [...$range, 'therapist_id' => $me->staffProfile->getKey()])
            ->assertHasNoActionErrors();

        $this->assertDatabaseMissing('therapist_work_blocks', ['id' => $mine->getKey()]);
        $this->assertDatabaseHas('therapist_work_blocks', ['id' => $theirs->getKey()]);
    }

    public function test_changing_own_working_hours_notifies_the_admins_in_app(): void
    {
        $me = $this->therapist();
        $admin = User::factory()->admin()->create();
        $block = $this->makeWorkBlock($me->staffProfile);

        $this->actingAs($me);

        Livewire::test(ReservationCalendar::class)
            ->set('mode', 'template')
            ->set('editingTemplateId', (string) $block->getKey())
            ->mountAction('editSchedule')
            ->setActionData([
                'therapist_id' => $me->staffProfile->getKey(),
                'room_id' => $this->room->getKey(),
                'work_date' => $this->monday->toDateString(),
                'start_time' => '09:00',
                'end_time' => '13:00',
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame('13:00', $block->fresh()->end_time);
        $this->assertSame(1, $admin->notifications()->count());
        $this->assertStringContainsString(
            $me->full_name,
            (string) data_get($admin->notifications()->sole()->data, 'title'),
        );
    }

    public function test_an_admin_changing_working_hours_notifies_nobody(): void
    {
        $admin = User::factory()->admin()->create();
        $therapist = $this->therapist();
        $block = $this->makeWorkBlock($therapist->staffProfile);

        $this->actingAs($admin);

        Livewire::test(ReservationCalendar::class)
            ->set('mode', 'template')
            ->set('editingTemplateId', (string) $block->getKey())
            ->mountAction('editSchedule')
            ->setActionData([
                'therapist_id' => $therapist->staffProfile->getKey(),
                'room_id' => $this->room->getKey(),
                'work_date' => $this->monday->toDateString(),
                'start_time' => '09:00',
                'end_time' => '13:00',
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame(0, $admin->notifications()->count());
    }

    public function test_a_blocking_someone_else_put_on_the_grid_is_marked_read_only(): void
    {
        $me = $this->therapist();
        $rental = $this->makeBlocking(null);
        $colleagues = $this->makeBlocking($this->therapist(), '16:00');
        $mine = $this->makeBlocking($me, '17:00');

        $this->actingAs($me);

        $events = $this->fetchWeek(new ReservationCalendar);

        $this->assertTrue($this->eventFor($events, 'blocking:'.$rental->getKey())['extendedProps']['isForeign']);
        $this->assertTrue($this->eventFor($events, 'blocking:'.$colleagues->getKey())['extendedProps']['isForeign']);
        $this->assertFalse($this->eventFor($events, 'blocking:'.$mine->getKey())['extendedProps']['isForeign']);
    }

    public function test_an_admin_owns_every_blocking(): void
    {
        $rental = $this->makeBlocking(null);

        $this->actingAs(User::factory()->admin()->create());

        $event = $this->eventFor($this->fetchWeek(new ReservationCalendar), 'blocking:'.$rental->getKey());

        $this->assertFalse($event['extendedProps']['isForeign']);
    }

    public function test_a_therapist_cannot_open_a_blocking_they_did_not_create(): void
    {
        $me = $this->therapist();
        $rental = $this->makeBlocking(null);
        $mine = $this->makeBlocking($me, '17:00');

        $this->actingAs($me);

        Livewire::test(ReservationCalendar::class)
            ->call('onEventClick', ['id' => 'blocking:'.$rental->getKey()])
            ->assertSet('editingTemplateId', null)
            ->assertActionNotMounted('editOneTimeBlocking')
            ->call('onEventClick', ['id' => 'blocking:'.$mine->getKey()])
            ->assertSet('editingTemplateId', (string) $mine->getKey())
            ->assertActionMounted('editOneTimeBlocking');
    }

    public function test_a_therapist_cannot_bulk_delete_a_blocking_they_did_not_create(): void
    {
        $me = $this->therapist();
        $rental = $this->makeBlocking(null);
        $mine = $this->makeBlocking($me, '17:00');

        $this->actingAs($me);

        // The selection is a browser-side list, so it is re-checked on delete
        // even though a foreign card cannot be selected in the first place.
        Livewire::test(ReservationCalendar::class)
            ->set('mode', 'template')
            ->set('selectionMode', true)
            ->set('selectedIds', ['blocking:'.$rental->getKey(), 'blocking:'.$mine->getKey()])
            ->callAction('deleteSelectedTemplate')
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('room_blockings', ['id' => $rental->getKey()]);
        $this->assertDatabaseMissing('room_blockings', ['id' => $mine->getKey()]);
    }

    public function test_a_therapist_cannot_edit_or_delete_a_foreign_blocking_through_its_modal(): void
    {
        $me = $this->therapist();
        $rental = $this->makeBlocking(null);

        $this->actingAs($me);

        Livewire::test(ReservationCalendar::class)
            ->set('editingTemplateId', (string) $rental->getKey())
            ->mountAction('editOneTimeBlocking')
            ->setActionData([
                'room_id' => $this->room->getKey(),
                'start_at' => $this->monday->copy()->setTimeFromTimeString('08:00'),
                'end_at' => $this->monday->copy()->setTimeFromTimeString('09:00'),
                'reason' => 'Přepsáno',
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame('Kuba – pronájem', $rental->fresh()->reason);

        Livewire::test(ReservationCalendar::class)
            ->set('editingTemplateId', (string) $rental->getKey())
            ->mountAction('editOneTimeBlocking')
            ->callAction(TestAction::make('deleteOneTimeBlocking'));

        $this->assertDatabaseHas('room_blockings', ['id' => $rental->getKey()]);
    }

    public function test_a_therapist_manages_the_blockings_they_created(): void
    {
        $me = $this->therapist();

        $this->actingAs($me);

        // Anything a therapist adds is stamped as theirs.
        Livewire::test(ReservationCalendar::class)
            ->callAction('addOneTimeBlocking', [
                'room_id' => $this->room->getKey(),
                'start_at' => $this->monday->copy()->setTimeFromTimeString('15:00'),
                'end_at' => $this->monday->copy()->setTimeFromTimeString('16:00'),
                'reason' => 'Vlastní blokace',
            ])
            ->assertHasNoActionErrors();

        $blocking = RoomBlocking::sole();
        $this->assertSame($me->getKey(), $blocking->created_by);

        Livewire::test(ReservationCalendar::class)
            ->set('editingTemplateId', (string) $blocking->getKey())
            ->mountAction('editOneTimeBlocking')
            ->callAction(TestAction::make('deleteOneTimeBlocking'));

        $this->assertDatabaseMissing('room_blockings', ['id' => $blocking->getKey()]);
    }

    public function test_a_therapist_cannot_delete_a_colleagues_working_hours_through_its_modal(): void
    {
        $me = $this->therapist();
        $theirs = $this->makeWorkBlock($this->therapist()->staffProfile);

        $this->actingAs($me);

        Livewire::test(ReservationCalendar::class)
            ->set('mode', 'template')
            ->set('editingTemplateId', (string) $theirs->getKey())
            ->mountAction('editSchedule')
            ->callAction(TestAction::make('deleteSchedule'));

        $this->assertDatabaseHas('therapist_work_blocks', ['id' => $theirs->getKey()]);
    }

    public function test_a_therapist_cannot_open_a_colleagues_visit_by_dragging_it(): void
    {
        $me = $this->therapist();
        $theirs = $this->makeReservation($this->therapist()->staffProfile);

        $this->actingAs($me);

        $component = Livewire::test(ReservationCalendar::class)
            ->call('onEventDrop', ['id' => (string) $theirs->getKey()], [], [], [], null, null)
            ->assertActionNotMounted('edit');

        $this->assertSame([], $component->instance()->mountedActions);
    }

    public function test_a_lecturer_is_scoped_too_and_an_admin_who_practises_is_not(): void
    {
        $lecturer = User::factory()->create();
        $lecturer->grantCapability(Capability::Lecturer);

        $this->actingAs($lecturer->refresh());
        $this->assertTrue((new ReservationCalendar)->isScopedStaff());

        $this->actingAs(User::factory()->admin()->therapist()->create());
        $this->assertFalse((new ReservationCalendar)->isScopedStaff());
    }
}
