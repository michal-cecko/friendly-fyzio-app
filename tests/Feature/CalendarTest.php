<?php

namespace Tests\Feature;

use App\Enums\EmailTemplateKey;
use App\Enums\ReservationStatus;
use App\Filament\Clusters\Kurzy\Resources\Lessons\LessonResource;
use App\Filament\Clusters\Provoz\Resources\Reservations\Pages\EditReservation;
use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Filament\Widgets\ReservationCalendar;
use App\Models\Building;
use App\Models\CourseSeries;
use App\Models\Lesson;
use App\Models\Reservation;
use App\Models\ReservationDayWaitlistEntry;
use App\Models\Room;
use App\Models\Service;
use App\Models\Setting;
use App\Models\StaffProfile;
use App\Models\User;
use App\Notifications\ReservationTemplateNotification;
use App\Support\Settings;
use Database\Seeders\SettingsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    protected function makeReservation(array $overrides = []): Reservation
    {
        $building = Building::create(['name' => 'Budova', 'address' => 'Adresa']);
        $room = Room::create(['building_id' => $building->getKey(), 'name' => 'Sál']);
        $therapist = StaffProfile::create(['user_id' => User::factory()->therapist()->create()->getKey()]);
        $service = Service::factory()->create();
        $client = User::factory()->customer()->create();

        return Reservation::factory()->create(array_merge([
            'client_id' => $client->getKey(),
            'service_id' => $service->getKey(),
            'therapist_id' => $therapist->getKey(),
            'room_id' => $room->getKey(),
        ], $overrides));
    }

    protected function fetchWeek(ReservationCalendar $calendar): array
    {
        $monday = Carbon::now()->startOfWeek(Carbon::MONDAY);

        return $calendar->fetchEvents([
            'start' => $monday->toDateString(),
            'end' => $monday->copy()->addDays(7)->toDateString(),
            'timezone' => config('app.timezone'),
        ]);
    }

    public function test_calendar_page_loads_for_admin(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/calendar')
            ->assertSuccessful();
    }

    public function test_calendar_widget_renders_filament_filters_form(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ReservationCalendar::class)
            ->assertSuccessful()
            ->assertSee('Klient')
            ->assertSee('Místnost')
            ->assertSee('Stav')
            ->assertSee('Služba')
            ->assertSee('Smazané');
    }

    public function test_therapist_chips_are_labelled_with_the_given_name_only(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $therapist = User::factory()->therapist()->create(['name' => 'Lucie Fičkerová']);
        StaffProfile::factory()->published()->create(['user_id' => $therapist->getKey()]);

        $html = Livewire::test(ReservationCalendar::class)->assertSuccessful()->html();

        // Chip label short, avatar initials and the tooltip still complete.
        $this->assertStringContainsString('<span>Lucie</span>', $html);
        $this->assertStringNotContainsString('<span>Lucie Fičkerová</span>', $html);
        $this->assertStringContainsString('>LF</span>', $html);
        $this->assertStringContainsString('title="Lucie Fičkerová"', $html);
    }

    public function test_side_panel_toggle_is_labelled_and_starts_collapsed_on_small_screens(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $html = Livewire::test(ReservationCalendar::class)->assertSuccessful()->html();

        // Both arrow buttons now say what they do…
        $this->assertStringContainsString('<span>Zobrazit kalendář</span>', $html);
        $this->assertStringContainsString('<span>Skrýt kalendář</span>', $html);
        // …and the open/closed state is client side, so phones can default to closed.
        $this->assertStringContainsString('sideOpen: window.innerWidth > 1024', $html);
        $this->assertStringContainsString('x-show="sideOpen"', $html);
        $this->assertStringContainsString('x-show="! sideOpen"', $html);
    }

    public function test_client_filter_limits_events(): void
    {
        $monday = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $a = $this->makeReservation(['reservation_date' => $monday->toDateString()]);
        $b = $this->makeReservation(['reservation_date' => $monday->toDateString()]);

        $calendar = new ReservationCalendar;
        $calendar->filterData = ['clientIds' => [(string) $a->client_id]];
        $ids = array_column($this->fetchWeek($calendar), 'id');

        $this->assertContains($a->getKey(), $ids);
        $this->assertNotContains($b->getKey(), $ids);
    }

    public function test_active_filter_count_reflects_select_filters(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $component = Livewire::test(ReservationCalendar::class);
        $this->assertSame(0, $component->instance()->activeFilterCount());

        $component->set('filterData', ['statusIds' => [ReservationStatus::Confirmed->value], 'trashed' => 'only']);
        $this->assertSame(2, $component->instance()->activeFilterCount());
    }

    public function test_calendar_links_to_reservation_list(): void
    {
        // The inline "Seznam" list moved to the Rezervace resource index; the
        // calendar now links there instead of rendering a table inline.
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ReservationCalendar::class)
            ->assertSuccessful()
            ->assertSee(ReservationResource::getUrl('index'));
    }

    public function test_reset_filters_clears_all_filters(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ReservationCalendar::class)
            ->set('therapistIds', ['some-id'])
            ->set('search', 'abc')
            ->set('filterData', ['statusIds' => [ReservationStatus::Confirmed->value], 'trashed' => 'only'])
            ->call('resetFilters')
            ->assertSet('therapistIds', [])
            ->assertSet('search', '')
            ->assertSet('filterData.statusIds', [])
            ->assertSet('filterData.trashed', 'without');
    }

    public function test_reset_button_only_visible_with_active_filters(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ReservationCalendar::class)
            ->assertDontSee('Zrušit všechny filtry')
            ->set('search', 'abc')
            ->assertSee('Zrušit všechny filtry')
            ->call('resetFilters')
            ->assertDontSee('Zrušit všechny filtry');
    }

    public function test_mode_toggle_renders_template_toolbar(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ReservationCalendar::class)
            ->assertSuccessful()
            ->assertSee('Nová rezervace')
            ->set('mode', 'template')
            ->assertSuccessful()
            ->assertSee('Přidat pracovní dobu')
            ->assertSee('Smazat období');
    }

    public function test_selection_mode_click_toggles_selection_instead_of_editing(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $key = (string) $this->makeReservation()->getKey();

        Livewire::test(ReservationCalendar::class)
            ->call('toggleSelectionMode')
            ->assertSet('selectionMode', true)
            ->call('onEventClick', ['id' => $key])
            ->assertSet('selectedIds', [$key])
            ->call('onEventClick', ['id' => $key])
            ->assertSet('selectedIds', []);
    }

    public function test_clicking_a_trashed_reservation_opens_the_edit_modal(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $reservation = $this->makeReservation();
        $reservation->delete();

        Livewire::test(ReservationCalendar::class)
            ->call('onEventClick', ['id' => (string) $reservation->getKey()])
            ->assertActionMounted('edit');
    }

    public function test_restoring_a_reservation_from_the_edit_modal(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->admin()->create());

        $reservation = $this->makeReservation([
            'status' => ReservationStatus::Cancelled,
            'reservation_date' => today()->addDays(10)->toDateString(),
        ]);
        $reservation->delete();

        Livewire::test(ReservationCalendar::class)
            ->call('onEventClick', ['id' => (string) $reservation->getKey()])
            ->mountAction('restoreReservation')
            ->callMountedAction();

        $reservation = $reservation->fresh();

        $this->assertFalse($reservation->trashed());
        $this->assertSame(ReservationStatus::Pending, $reservation->status);
    }

    public function test_selection_persists_across_week_navigation(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $monday = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $key = (string) $this->makeReservation(['reservation_date' => $monday->toDateString()])->getKey();

        Livewire::test(ReservationCalendar::class)
            ->set('selectionMode', true)
            ->set('selectedIds', [$key])
            ->call('fetchEvents', [
                'start' => $monday->copy()->addWeek()->toDateString(),
                'end' => $monday->copy()->addWeeks(2)->toDateString(),
                'timezone' => config('app.timezone'),
            ])
            ->assertSet('selectedIds', [$key]);
    }

    public function test_cancel_selected_action_cancels_reservations_and_notifies(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->admin()->create());
        $a = $this->makeReservation(['status' => ReservationStatus::Confirmed]);
        $b = $this->makeReservation(['status' => ReservationStatus::Confirmed]);

        Livewire::test(ReservationCalendar::class)
            ->set('selectedIds', [(string) $a->getKey()])
            ->callAction('cancelSelected', ['notify_client' => true, 'cancellation_reason' => 'Terapeut nemocný'])
            ->assertSet('selectedIds', []);

        $a->refresh();
        $this->assertSame(ReservationStatus::Cancelled, $a->status);
        $this->assertSame('Terapeut nemocný', $a->cancellation_reason);
        $this->assertSame(ReservationStatus::Confirmed, $b->refresh()->status);
        Notification::assertSentTo($a->client, ReservationTemplateNotification::class, function (ReservationTemplateNotification $n): bool {
            return $n->key === EmailTemplateKey::ReservationCancelled;
        });
    }

    public function test_cancel_selected_with_erase_opt_in_notifies_and_moves_records_to_the_trash(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->admin()->create());
        $reservation = $this->makeReservation(['status' => ReservationStatus::Confirmed]);
        $client = $reservation->client;

        Livewire::test(ReservationCalendar::class)
            ->set('selectedIds', [(string) $reservation->getKey()])
            ->callAction('cancelSelected', [
                'cancellation_reason' => 'Duplicitní rezervace',
                'force_delete' => true,
                'notify_client' => true,
            ])
            ->assertSet('selectedIds', []);

        // Same modal + semantics as the reservations list: the client is told
        // about an ordinary cancellation and the record moves to the trash,
        // from where the daily prune erases it after 30 days.
        Notification::assertSentTo($client, ReservationTemplateNotification::class, function (ReservationTemplateNotification $n): bool {
            return $n->key === EmailTemplateKey::ReservationCancelled;
        });
        $this->assertTrue(Reservation::withTrashed()->find($reservation->getKey())->trashed());
    }

    public function test_restore_selected_action_restores_trashed_reservations(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->admin()->create());
        $a = $this->makeReservation([
            'status' => ReservationStatus::Cancelled,
            'cancellation_reason' => 'Zrušeno klientem',
            'reservation_date' => today()->addDays(10)->toDateString(),
        ]);
        $a->delete();

        Livewire::test(ReservationCalendar::class)
            ->set('filterData', ['trashed' => 'only'])
            ->set('selectedIds', [(string) $a->getKey()])
            ->callAction('restoreSelected', ['notify_client' => true]);

        // Restore reactivates like the list action: undeleted, back to Pending
        // (visit outside the confirmation window), reason cleared, client thanked.
        $a = $a->fresh();

        $this->assertFalse($a->trashed());
        $this->assertSame(ReservationStatus::Pending, $a->status);
        $this->assertNull($a->cancellation_reason);
        Notification::assertSentTo($a->client, ReservationTemplateNotification::class, function (ReservationTemplateNotification $n): bool {
            return $n->key === EmailTemplateKey::ReservationCreated;
        });
    }

    public function test_restore_action_is_available_outside_the_trashed_view_for_cancelled_events(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $cancelled = $this->makeReservation([
            'reservation_date' => Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString(),
            'status' => ReservationStatus::Cancelled,
        ]);

        // The restore bulk action appears once the selection contains a restorable
        // (trashed or cancelled) reservation — even outside the trashed view.
        Livewire::test(ReservationCalendar::class)
            ->set('selectionMode', true)
            ->assertActionHidden('restoreSelected')
            ->call('onEventClick', ['id' => (string) $cancelled->getKey()])
            ->assertActionVisible('restoreSelected');
    }

    public function test_filters_and_view_hydrate_from_url_query_params(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::withQueryParams([
            'filters' => ['statusIds' => [ReservationStatus::Confirmed->value]],
            'therapists' => ['therapist-id'],
            'q' => 'novak',
            'mode' => 'template',
            'date' => '2026-06-04',
        ])
            ->test(ReservationCalendar::class)
            ->assertSet('filterData.statusIds', [ReservationStatus::Confirmed->value])
            ->assertSet('filterData.trashed', 'without')
            ->assertSet('therapistIds', ['therapist-id'])
            ->assertSet('search', 'novak')
            ->assertSet('mode', 'template')
            ->assertSet('calendarDate', '2026-06-04');
    }

    public function test_fetch_events_returns_week_reservations(): void
    {
        $monday = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $reservation = $this->makeReservation([
            'reservation_date' => $monday->copy()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);

        $events = $this->fetchWeek(new ReservationCalendar);

        $this->assertContains($reservation->getKey(), array_column($events, 'id'));
    }

    /**
     * @return array<string, mixed>
     */
    private function eventFor(Reservation $reservation): array
    {
        return collect($this->fetchWeek(new ReservationCalendar))
            ->firstWhere('id', $reservation->getKey());
    }

    public function test_a_reservation_with_a_break_carries_its_strip(): void
    {
        $reservation = $this->makeReservation([
            'reservation_date' => Carbon::now()->startOfWeek(Carbon::MONDAY)->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'break_minutes' => 15,
            'status' => ReservationStatus::Confirmed,
        ]);

        $props = $this->eventFor($reservation)['extendedProps'];

        $this->assertTrue($props['hasBreak']);
        $this->assertSame(15, $props['breakMinutes']);
        // A quarter of a 60-minute card, which in a timegrid is a quarter of its
        // height — that ratio is what sizes the strip.
        $this->assertSame(0.25, $props['breakRatio']);
        $this->assertSame('Pauza 15 min', $props['breakLabel']);
        $this->assertSame('10:15', $props['breakUntil']);
    }

    public function test_the_event_itself_still_ends_when_the_visit_does(): void
    {
        // The strip overhangs the card; it must not stretch the booking, or the
        // slot engine's picture and the calendar's would disagree.
        $reservation = $this->makeReservation([
            'reservation_date' => Carbon::now()->startOfWeek(Carbon::MONDAY)->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'break_minutes' => 15,
            'status' => ReservationStatus::Confirmed,
        ]);

        $events = $this->fetchWeek(new ReservationCalendar);
        $mine = array_values(array_filter($events, fn (array $event): bool => $event['id'] === $reservation->getKey()));

        // Exactly one event, ending at the visit's end and not the break's.
        $this->assertCount(1, $mine);
        $this->assertSame($reservation->reservation_date->toDateString().'T10:00', $mine[0]['end']);
    }

    public function test_no_break_strip_without_a_break_or_once_the_visit_is_cancelled(): void
    {
        $monday = Carbon::now()->startOfWeek(Carbon::MONDAY);

        $noBreak = $this->makeReservation([
            'reservation_date' => $monday->copy()->addDay()->toDateString(),
            'break_minutes' => 0,
            'status' => ReservationStatus::Confirmed,
        ]);
        $cancelled = $this->makeReservation([
            'reservation_date' => $monday->copy()->addDay()->toDateString(),
            'break_minutes' => 15,
            'status' => ReservationStatus::Cancelled,
        ]);

        $this->assertFalse($this->eventFor($noBreak)['extendedProps']['hasBreak']);
        $this->assertFalse($this->eventFor($cancelled)['extendedProps']['hasBreak']);
    }

    public function test_therapist_list_shows_only_current_published_therapists_and_lecturers(): void
    {
        $publishedTherapist = StaffProfile::create([
            'user_id' => User::factory()->therapist()->create()->getKey(),
            'published_at' => now(),
        ]);
        $publishedLecturer = StaffProfile::create([
            'user_id' => User::factory()->lecturer()->create()->getKey(),
            'published_at' => now(),
        ]);

        // Excluded: unpublished (historical import), deactivated ex-staff, and an
        // admin/assistant profile that neither treats nor teaches.
        $unpublishedLecturer = StaffProfile::create([
            'user_id' => User::factory()->lecturer()->create()->getKey(),
        ]);
        $deactivatedTherapist = StaffProfile::create([
            'user_id' => User::factory()->therapist()->create(['deactivated_at' => now()])->getKey(),
            'published_at' => now(),
        ]);
        $adminOnly = StaffProfile::create([
            'user_id' => User::factory()->admin()->create()->getKey(),
            'published_at' => now(),
        ]);

        $ids = (new ReservationCalendar)->therapists()->pluck('id')->all();

        $this->assertContains($publishedTherapist->getKey(), $ids);
        $this->assertContains($publishedLecturer->getKey(), $ids);
        $this->assertNotContains($unpublishedLecturer->getKey(), $ids);
        $this->assertNotContains($deactivatedTherapist->getKey(), $ids);
        $this->assertNotContains($adminOnly->getKey(), $ids);
    }

    public function test_therapist_filter_limits_events(): void
    {
        $monday = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $a = $this->makeReservation(['reservation_date' => $monday->toDateString()]);
        $b = $this->makeReservation(['reservation_date' => $monday->toDateString()]);

        $calendar = new ReservationCalendar;
        $calendar->therapistIds = [$a->therapist_id];
        $ids = array_column($this->fetchWeek($calendar), 'id');

        $this->assertContains($a->getKey(), $ids);
        $this->assertNotContains($b->getKey(), $ids);
    }

    public function test_service_filter_limits_events(): void
    {
        $monday = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $a = $this->makeReservation(['reservation_date' => $monday->toDateString()]);
        $b = $this->makeReservation(['reservation_date' => $monday->toDateString()]);

        $calendar = new ReservationCalendar;
        $calendar->filterData = ['serviceIds' => [$a->service_id]];
        $ids = array_column($this->fetchWeek($calendar), 'id');

        $this->assertContains($a->getKey(), $ids);
        $this->assertNotContains($b->getKey(), $ids);
    }

    public function test_status_filter_limits_events(): void
    {
        $monday = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $confirmed = $this->makeReservation(['reservation_date' => $monday->toDateString(), 'status' => ReservationStatus::Confirmed]);
        $pending = $this->makeReservation(['reservation_date' => $monday->toDateString(), 'status' => ReservationStatus::Pending]);

        $calendar = new ReservationCalendar;
        $calendar->filterData = ['statusIds' => [ReservationStatus::Confirmed->value]];
        $ids = array_column($this->fetchWeek($calendar), 'id');

        $this->assertContains($confirmed->getKey(), $ids);
        $this->assertNotContains($pending->getKey(), $ids);
    }

    public function test_search_filters_by_client_name(): void
    {
        $monday = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $client = User::factory()->customer()->create(['name' => 'Zvláštní Pacient']);
        $match = $this->makeReservation(['reservation_date' => $monday->toDateString(), 'client_id' => $client->getKey()]);
        $other = $this->makeReservation(['reservation_date' => $monday->toDateString()]);

        $calendar = new ReservationCalendar;
        $calendar->search = 'zvláštní';
        $ids = array_column($this->fetchWeek($calendar), 'id');

        $this->assertContains($match->getKey(), $ids);
        $this->assertNotContains($other->getKey(), $ids);
    }

    public function test_trashed_filter_controls_visibility(): void
    {
        $monday = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $active = $this->makeReservation(['reservation_date' => $monday->toDateString()]);
        $deleted = $this->makeReservation(['reservation_date' => $monday->toDateString()]);
        $deleted->delete();

        $default = new ReservationCalendar;
        $defaultIds = array_column($this->fetchWeek($default), 'id');
        $this->assertContains($active->getKey(), $defaultIds);
        $this->assertNotContains($deleted->getKey(), $defaultIds);

        $only = new ReservationCalendar;
        $only->filterData = ['trashed' => 'only'];
        $onlyIds = array_column($this->fetchWeek($only), 'id');
        $this->assertContains($deleted->getKey(), $onlyIds);
        $this->assertNotContains($active->getKey(), $onlyIds);

        $with = new ReservationCalendar;
        $with->filterData = ['trashed' => 'with'];
        $withIds = array_column($this->fetchWeek($with), 'id');
        $this->assertContains($active->getKey(), $withIds);
        $this->assertContains($deleted->getKey(), $withIds);
    }

    // ---- Course lesson & one-off event overlays -------------------------------

    /**
     * A lesson belonging to a course série — the "Kurzy" overlay.
     */
    protected function makeSeriesLesson(array $overrides = []): Lesson
    {
        return Lesson::factory()->create(array_merge([
            'lesson_date' => Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString(),
        ], $overrides));
    }

    /**
     * A standalone workshop / jednorázová lekce — the "Jednorázové" overlay.
     */
    protected function makeStandaloneLesson(array $overrides = []): Lesson
    {
        return Lesson::factory()->standalone()->published()->create(array_merge([
            'lesson_date' => Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString(),
        ], $overrides));
    }

    public function test_fetch_events_includes_lessons_and_lessons(): void
    {
        $reservation = $this->makeReservation(['reservation_date' => Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString()]);
        $lesson = $this->makeSeriesLesson();
        $event = $this->makeStandaloneLesson();

        $events = collect($this->fetchWeek(new ReservationCalendar));
        $ids = $events->pluck('id')->all();

        $this->assertContains($reservation->getKey(), $ids);
        $this->assertContains('course:'.$lesson->getKey(), $ids);
        $this->assertContains('oneoff:'.$event->getKey(), $ids);

        $lessonEvent = $events->firstWhere('id', 'course:'.$lesson->getKey());
        $this->assertSame('course', $lessonEvent['extendedProps']['kind']);
        $this->assertFalse($lessonEvent['editable']);
        $this->assertSame(LessonResource::getUrl('view', ['record' => $lesson]), $lessonEvent['url']);

        $lesson = $events->firstWhere('id', 'oneoff:'.$event->getKey());
        $this->assertSame('oneOffEvent', $lesson['extendedProps']['kind']);
        $this->assertFalse($lesson['editable']);
        $this->assertSame(LessonResource::getUrl('view', ['record' => $event]), $lesson['url']);
    }

    public function test_course_and_event_overlays_render_in_the_working_hours_mode_too(): void
    {
        // Working hours are laid out against whatever already competes with them,
        // so the same read-only overlays appear in both modes. Reservations do
        // not: that grid is the availability template, not the bookings in it.
        $lesson = $this->makeSeriesLesson();
        $event = $this->makeStandaloneLesson();

        $calendar = new ReservationCalendar;
        $calendar->mode = 'template';
        $events = collect($this->fetchWeek($calendar));
        $ids = $events->pluck('id')->all();

        $this->assertContains('course:'.$lesson->getKey(), $ids);
        $this->assertContains('oneoff:'.$event->getKey(), $ids);

        // Still read-only, still linking straight to the lesson detail.
        $lessonEvent = $events->firstWhere('id', 'course:'.$lesson->getKey());
        $this->assertFalse($lessonEvent['editable']);
        $this->assertSame(LessonResource::getUrl('view', ['record' => $lesson]), $lessonEvent['url']);
    }

    public function test_courses_toggle_hides_only_lessons(): void
    {
        $reservation = $this->makeReservation(['reservation_date' => Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString()]);
        $lesson = $this->makeSeriesLesson();
        $event = $this->makeStandaloneLesson();

        $calendar = new ReservationCalendar;
        $calendar->showCourses = false;
        $ids = array_column($this->fetchWeek($calendar), 'id');

        $this->assertContains($reservation->getKey(), $ids);
        $this->assertNotContains('course:'.$lesson->getKey(), $ids);
        $this->assertContains('oneoff:'.$event->getKey(), $ids);
    }

    public function test_lessons_toggle_hides_only_events(): void
    {
        $reservation = $this->makeReservation(['reservation_date' => Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString()]);
        $lesson = $this->makeSeriesLesson();
        $event = $this->makeStandaloneLesson();

        $calendar = new ReservationCalendar;
        $calendar->showLessons = false;
        $ids = array_column($this->fetchWeek($calendar), 'id');

        $this->assertContains($reservation->getKey(), $ids);
        $this->assertContains('course:'.$lesson->getKey(), $ids);
        $this->assertNotContains('oneoff:'.$event->getKey(), $ids);
    }

    public function test_reservations_toggle_hides_only_reservations(): void
    {
        $reservation = $this->makeReservation(['reservation_date' => Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString()]);
        $lesson = $this->makeSeriesLesson();
        $event = $this->makeStandaloneLesson();

        $calendar = new ReservationCalendar;
        $calendar->showReservations = false;
        $ids = array_column($this->fetchWeek($calendar), 'id');

        $this->assertNotContains($reservation->getKey(), $ids);
        $this->assertContains('course:'.$lesson->getKey(), $ids);
        $this->assertContains('oneoff:'.$event->getKey(), $ids);
        $this->assertSame(0, $calendar->weekCount);
    }

    public function test_room_filter_applies_to_lessons_and_events(): void
    {
        $roomA = Room::factory()->create();
        $roomB = Room::factory()->create();
        $lessonA = $this->makeSeriesLesson(['room_id' => $roomA->getKey()]);
        $lessonB = $this->makeSeriesLesson(['room_id' => $roomB->getKey()]);
        $eventA = $this->makeStandaloneLesson(['room_id' => $roomA->getKey()]);
        $eventB = $this->makeStandaloneLesson(['room_id' => $roomB->getKey()]);

        $calendar = new ReservationCalendar;
        $calendar->filterData = ['roomIds' => [(string) $roomA->getKey()]];
        $ids = array_column($this->fetchWeek($calendar), 'id');

        $this->assertContains('course:'.$lessonA->getKey(), $ids);
        $this->assertContains('oneoff:'.$eventA->getKey(), $ids);
        $this->assertNotContains('course:'.$lessonB->getKey(), $ids);
        $this->assertNotContains('oneoff:'.$eventB->getKey(), $ids);
    }

    public function test_therapist_chip_filters_lessons_and_events_by_instructor_user(): void
    {
        $instructor = User::factory()->therapist()->create();
        $profile = StaffProfile::create(['user_id' => $instructor->getKey(), 'published_at' => now()]);
        $matchingLesson = $this->makeSeriesLesson(['instructor_id' => $instructor->getKey()]);
        $otherLesson = $this->makeSeriesLesson();
        $matchingEvent = $this->makeStandaloneLesson(['instructor_id' => $instructor->getKey()]);
        $otherEvent = $this->makeStandaloneLesson();

        $calendar = new ReservationCalendar;
        $calendar->therapistIds = [(string) $profile->getKey()];
        $ids = array_column($this->fetchWeek($calendar), 'id');

        $this->assertContains('course:'.$matchingLesson->getKey(), $ids);
        $this->assertContains('oneoff:'.$matchingEvent->getKey(), $ids);
        $this->assertNotContains('course:'.$otherLesson->getKey(), $ids);
        $this->assertNotContains('oneoff:'.$otherEvent->getKey(), $ids);
    }

    public function test_reservation_specific_filters_hide_lessons_and_events(): void
    {
        $reservation = $this->makeReservation(['reservation_date' => Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString()]);
        $lesson = $this->makeSeriesLesson();
        $event = $this->makeStandaloneLesson();

        $calendar = new ReservationCalendar;
        $calendar->filterData = ['clientIds' => [(string) $reservation->client_id]];
        $ids = array_column($this->fetchWeek($calendar), 'id');

        $this->assertContains($reservation->getKey(), $ids);
        $this->assertNotContains('course:'.$lesson->getKey(), $ids);
        $this->assertNotContains('oneoff:'.$event->getKey(), $ids);

        $calendar = new ReservationCalendar;
        $calendar->filterData = ['statusIds' => [ReservationStatus::Confirmed->value]];
        $ids = array_column($this->fetchWeek($calendar), 'id');

        $this->assertNotContains('course:'.$lesson->getKey(), $ids);
        $this->assertNotContains('oneoff:'.$event->getKey(), $ids);

        $calendar = new ReservationCalendar;
        $calendar->filterData = ['trashed' => 'only'];
        $ids = array_column($this->fetchWeek($calendar), 'id');

        $this->assertNotContains('course:'.$lesson->getKey(), $ids);
        $this->assertNotContains('oneoff:'.$event->getKey(), $ids);
    }

    public function test_search_matches_course_and_event_names(): void
    {
        $series = CourseSeries::factory()->create(['name' => 'Pilates pro pokročilé']);
        $matchingLesson = $this->makeSeriesLesson(['series_id' => $series->getKey()]);
        // CourseFactory can randomly generate a "Pilates …" course name, so pin
        // the control lesson's series to a course that never matches the search.
        $otherSeries = CourseSeries::factory()->create(['name' => 'Série podzim']);
        $otherSeries->course->update(['name' => 'Zdravá záda']);
        $otherLesson = $this->makeSeriesLesson(['series_id' => $otherSeries->getKey()]);
        $matchingEvent = $this->makeStandaloneLesson(['name' => 'Pilates workshop']);
        $otherEvent = $this->makeStandaloneLesson(['name' => 'Dýchací techniky']);

        $calendar = new ReservationCalendar;
        $calendar->search = 'pilates';
        $ids = array_column($this->fetchWeek($calendar), 'id');

        $this->assertContains('course:'.$matchingLesson->getKey(), $ids);
        $this->assertContains('oneoff:'.$matchingEvent->getKey(), $ids);
        $this->assertNotContains('course:'.$otherLesson->getKey(), $ids);
        $this->assertNotContains('oneoff:'.$otherEvent->getKey(), $ids);
    }

    public function test_trashed_lessons_are_excluded_and_unpublished_shown(): void
    {
        $unpublished = Lesson::factory()->standalone()->unpublished()->create([
            'lesson_date' => Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString(),
        ]);
        $trashed = $this->makeSeriesLesson();
        $trashed->delete();

        $events = collect($this->fetchWeek(new ReservationCalendar));
        $ids = $events->pluck('id')->all();

        $this->assertContains('oneoff:'.$unpublished->getKey(), $ids);
        $this->assertNotContains('oneoff:'.$trashed->getKey(), $ids);
        $this->assertTrue($events->firstWhere('id', 'oneoff:'.$unpublished->getKey())['extendedProps']['isUnpublished']);
    }

    public function test_clicking_course_or_event_redirects_to_its_view_page(): void
    {
        $lesson = $this->makeSeriesLesson();
        $event = $this->makeStandaloneLesson();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ReservationCalendar::class)
            ->call('onEventClick', ['id' => 'course:'.$lesson->getKey()])
            ->assertRedirect(LessonResource::getUrl('view', ['record' => $lesson]));

        Livewire::test(ReservationCalendar::class)
            ->call('onEventClick', ['id' => 'oneoff:'.$event->getKey()])
            ->assertRedirect(LessonResource::getUrl('view', ['record' => $event]));
    }

    public function test_selection_mode_ignores_course_and_event_clicks(): void
    {
        $lesson = $this->makeSeriesLesson();
        $event = $this->makeStandaloneLesson();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ReservationCalendar::class)
            ->set('selectionMode', true)
            ->call('onEventClick', ['id' => 'course:'.$lesson->getKey()])
            ->call('onEventClick', ['id' => 'oneoff:'.$event->getKey()])
            ->assertSet('selectedIds', [])
            ->assertNoRedirect();

        $calendar = new ReservationCalendar;
        $calendar->selectionMode = true;
        $events = collect($this->fetchWeek($calendar));

        $this->assertArrayNotHasKey('url', $events->firstWhere('id', 'course:'.$lesson->getKey()));
        $this->assertArrayNotHasKey('url', $events->firstWhere('id', 'oneoff:'.$event->getKey()));
    }

    // ---- Day-waitlist header strip --------------------------------------------

    public function test_waitlist_summary_lists_pending_entries_for_visible_week(): void
    {
        $monday = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $first = ReservationDayWaitlistEntry::factory()->create(['reservation_date' => $monday->toDateString()]);
        ReservationDayWaitlistEntry::factory()->create(['reservation_date' => $monday->toDateString()]);
        ReservationDayWaitlistEntry::factory()->create(['reservation_date' => $monday->copy()->addDays(2)->toDateString()]);
        ReservationDayWaitlistEntry::factory()->notified()->create(['reservation_date' => $monday->toDateString()]);
        ReservationDayWaitlistEntry::factory()->create(['reservation_date' => $monday->copy()->addDays(7)->toDateString()]);

        $summary = (new ReservationCalendar)->waitlistWeekSummary();

        $this->assertCount(2, $summary);
        $this->assertSame($monday->toDateString(), $summary[0]['date']);
        $this->assertSame(2, $summary[0]['count']);
        $this->assertSame($monday->copy()->addDays(2)->toDateString(), $summary[1]['date']);
        $this->assertSame(1, $summary[1]['count']);
        $this->assertStringContainsString($first->displayName(), $summary[0]['names']);
        $this->assertNotSame('', $summary[0]['label']);
    }

    public function test_waitlist_summary_counts_any_therapist_with_chips_active(): void
    {
        $monday = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $profile = StaffProfile::create(['user_id' => User::factory()->therapist()->create()->getKey()]);
        $forChip = ReservationDayWaitlistEntry::factory()->create([
            'therapist_id' => $profile->getKey(),
            'reservation_date' => $monday->toDateString(),
        ]);
        $forOther = ReservationDayWaitlistEntry::factory()->create(['reservation_date' => $monday->toDateString()]);
        $anyTherapist = ReservationDayWaitlistEntry::factory()->anyTherapist()->create(['reservation_date' => $monday->toDateString()]);

        $calendar = new ReservationCalendar;
        $calendar->therapistIds = [(string) $profile->getKey()];
        $summary = $calendar->waitlistWeekSummary();

        $this->assertCount(1, $summary);
        $this->assertSame(2, $summary[0]['count']);
        $this->assertStringContainsString($forChip->displayName(), $summary[0]['names']);
        $this->assertStringContainsString($anyTherapist->displayName(), $summary[0]['names']);
        $this->assertStringNotContainsString($forOther->displayName(), $summary[0]['names']);
    }

    public function test_waitlist_summary_hidden_by_toggle_template_mode_and_feature_flag(): void
    {
        ReservationDayWaitlistEntry::factory()->create([
            'reservation_date' => Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString(),
        ]);

        $calendar = new ReservationCalendar;
        $calendar->showWaitlist = false;
        $this->assertSame([], $calendar->waitlistWeekSummary());

        $calendar = new ReservationCalendar;
        $calendar->mode = 'template';
        $this->assertSame([], $calendar->waitlistWeekSummary());

        $this->seed(SettingsSeeder::class);
        Setting::query()->where('key', 'reservation.day_waitlist_enabled')->firstOrFail()->update(['value' => '0']);
        Cache::forget(Settings::CACHE_KEY);

        $this->assertSame([], (new ReservationCalendar)->waitlistWeekSummary());
    }

    public function test_creating_a_client_inline_from_the_calendar_create_modal(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ReservationCalendar::class)
            ->callAction([
                TestAction::make('create'),
                TestAction::make('createOption')->schemaComponent('client_id'),
            ], [
                'first_name' => 'Anna',
                'last_name' => 'Nováková',
                'no_email' => false,
                'email' => 'anna@example.com',
                'phone' => null,
            ])
            ->assertHasNoActionErrors();

        $client = User::query()->where('email', 'anna@example.com')->firstOrFail();
        $this->assertSame('Anna Nováková', $client->name);
        $this->assertTrue($client->isCustomer());
        $this->assertNotNull($client->clientProfile);
    }

    public function test_inline_client_without_email_gets_placeholder_address(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ReservationCalendar::class)
            ->callAction([
                TestAction::make('create'),
                TestAction::make('createOption')->schemaComponent('client_id'),
            ], [
                'first_name' => 'Anna',
                'last_name' => 'Nováková',
                'no_email' => true,
                'phone' => '+420 777 123 456',
            ])
            ->assertHasNoActionErrors();

        $client = User::query()->where('name', 'Anna Nováková')->firstOrFail();
        $this->assertMatchesRegularExpression('/^anna\.novakova\d{4}@friendlyfyzio\.cz$/', $client->email);
        $this->assertSame('+420 777 123 456', $client->phone);
        $this->assertTrue($client->isCustomer());
    }

    public function test_inline_client_requires_phone_when_no_email(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ReservationCalendar::class)
            ->callAction([
                TestAction::make('create'),
                TestAction::make('createOption')->schemaComponent('client_id'),
            ], [
                'first_name' => 'Anna',
                'last_name' => 'Nováková',
                'no_email' => true,
                'phone' => null,
            ])
            ->assertHasActionErrors(['phone' => 'required']);
    }

    public function test_inline_client_rejects_taken_email(): void
    {
        User::factory()->customer()->create(['email' => 'taken@example.com']);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ReservationCalendar::class)
            ->callAction([
                TestAction::make('create'),
                TestAction::make('createOption')->schemaComponent('client_id'),
            ], [
                'first_name' => 'Anna',
                'last_name' => 'Nováková',
                'no_email' => false,
                'email' => 'taken@example.com',
                'phone' => null,
            ])
            ->assertHasActionErrors(['email' => 'unique']);
    }

    public function test_inline_client_requires_email_or_checkbox(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ReservationCalendar::class)
            ->callAction([
                TestAction::make('create'),
                TestAction::make('createOption')->schemaComponent('client_id'),
            ], [
                'first_name' => 'Anna',
                'last_name' => 'Nováková',
                'no_email' => false,
                'email' => null,
                'phone' => null,
            ])
            ->assertHasActionErrors(['email' => 'required', 'phone' => 'required']);
    }

    public function test_create_modal_submit_button_says_vytvorit_a_zavrit(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ReservationCalendar::class)
            ->mountAction('create')
            ->assertMountedActionModalSee('Vytvořit a zavřít');
    }

    public function test_client_create_option_is_absent_on_the_reservation_edit_page(): void
    {
        $reservation = $this->makeReservation();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(EditReservation::class, ['record' => $reservation->getRouteKey()])
            ->assertActionDoesNotExist(TestAction::make('createOption')->schemaComponent('client_id'));
    }
}
