<?php

namespace Tests\Feature;

use App\Enums\EmailTemplateKey;
use App\Enums\ReservationStatus;
use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Filament\Widgets\ReservationCalendar;
use App\Models\Building;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Service;
use App\Models\TherapistProfile;
use App\Models\User;
use App\Notifications\ReservationTemplateNotification;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
        $therapist = TherapistProfile::create(['user_id' => User::factory()->therapist()->create()->getKey()]);
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

        // Cancelled (non-trashed) events are selectable in the normal view, so the
        // restore bulk action stays visible there — it merely skips active rows.
        Livewire::test(ReservationCalendar::class)
            ->set('selectionMode', true)
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
}
