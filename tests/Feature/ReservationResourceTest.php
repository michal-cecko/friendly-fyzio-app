<?php

namespace Tests\Feature;

use App\Enums\EmailTemplateKey;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Filament\Clusters\Provoz\Resources\Reservations\Pages\EditReservation;
use App\Filament\Clusters\Provoz\Resources\Reservations\Pages\ListReservations;
use App\Filament\Clusters\Provoz\Resources\Reservations\Widgets\ReservationStatsOverview;
use App\Filament\Widgets\ReservationCalendar;
use App\Models\Building;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use App\Notifications\ReservationNotification;
use App\Notifications\ReservationTemplateNotification;
use App\Notifications\TherapistReservationTemplateNotification;
use App\Support\Reservations\ReservationEmailContext;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\SettingsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ReservationResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    /**
     * @return array{client: User, service: Service, therapist: StaffProfile, room: Room}
     */
    protected function dependencies(): array
    {
        $building = Building::create(['name' => 'Budova', 'address' => 'Adresa']);
        $room = Room::create(['building_id' => $building->getKey(), 'name' => 'Sál']);
        $therapist = StaffProfile::create(['user_id' => User::factory()->therapist()->create()->getKey()]);

        return [
            'client' => User::factory()->customer()->create(),
            'service' => Service::factory()->create(),
            'therapist' => $therapist,
            'room' => $room,
        ];
    }

    /**
     * @param  array{client: User, service: Service, therapist: StaffProfile, room: Room}  $deps
     * @param  array<string, mixed>  $overrides
     */
    protected function makeReservation(array $deps, array $overrides = []): Reservation
    {
        return Reservation::create([
            'client_id' => $deps['client']->getKey(),
            'service_id' => $deps['service']->getKey(),
            'therapist_id' => $deps['therapist']->getKey(),
            'room_id' => $deps['room']->getKey(),
            'reservation_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => ReservationStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
            ...$overrides,
        ]);
    }

    /**
     * @param  array{client: User, service: Service, therapist: StaffProfile, room: Room}  $deps
     * @return array<string, mixed>
     */
    protected function formData(array $deps, array $overrides = []): array
    {
        return [
            'client_id' => $deps['client']->getKey(),
            'service_id' => $deps['service']->getKey(),
            'therapist_id' => $deps['therapist']->getKey(),
            'room_id' => $deps['room']->getKey(),
            'reservation_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'notify_client' => true,
            ...$overrides,
        ];
    }

    public function test_admin_can_view_reservations_list(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/provoz/reservations')
            ->assertSuccessful();
    }

    public function test_date_and_time_are_merged_into_one_column_and_secondary_columns_are_hidden(): void
    {
        $deps = $this->dependencies();
        $this->actingAs(User::factory()->admin()->create());

        $reservation = $this->makeReservation($deps, [
            'reservation_date' => '2026-08-15',
            'start_time' => '09:30:00',
        ]);

        Livewire::test(ListReservations::class)
            ->assertCanSeeTableRecords([$reservation])
            ->assertSee('15.08.2026 09:30')
            ->assertCanRenderTableColumn('reservation_date')
            // These stay off until toggled on via the column toggle menu.
            ->assertCanNotRenderTableColumn('payment_status')
            ->assertCanNotRenderTableColumn('confirmed_by')
            ->assertCanNotRenderTableColumn('doctor_note_requested_at');
    }

    public function test_reservations_can_be_sorted_by_the_merged_date_and_time_column(): void
    {
        $deps = $this->dependencies();
        $this->actingAs(User::factory()->admin()->create());

        $earlier = $this->makeReservation($deps, ['reservation_date' => '2026-08-15', 'start_time' => '08:00:00', 'end_time' => '09:00:00']);
        $later = $this->makeReservation($deps, ['reservation_date' => '2026-08-15', 'start_time' => '10:00:00', 'end_time' => '11:00:00']);

        Livewire::test(ListReservations::class)
            ->sortTable('reservation_date')
            ->assertCanSeeTableRecords([$earlier, $later], inOrder: true);
    }

    public function test_edit_page_saves_changes(): void
    {
        $deps = $this->dependencies();
        $this->actingAs(User::factory()->admin()->create());

        $reservation = $this->makeReservation($deps, ['start_time' => '09:00:00', 'end_time' => '10:00:00']);

        Livewire::test(EditReservation::class, ['record' => $reservation->getKey()])
            ->fillForm(['end_time' => '11:00'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('11:00', substr((string) $reservation->fresh()->end_time, 0, 5));
    }

    public function test_editing_the_termin_prompts_and_emails_client_and_therapist_with_original_values(): void
    {
        Notification::fake();

        $deps = $this->dependencies();
        $this->actingAs(User::factory()->admin()->create());

        $reservation = $this->makeReservation($deps);
        $originalWhen = ReservationEmailContext::formatWhen($reservation);

        $page = Livewire::test(EditReservation::class, ['record' => $reservation->getKey()])
            ->fillForm(['end_time' => '11:00'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertActionMounted('scheduleChangeNotification');

        // Nothing is sent on save alone — the notification is opt-in via the prompt.
        Notification::assertNothingSent();

        $page->setActionData(['reason' => 'Konec posouváme o hodinu.'])
            ->callMountedAction();

        Notification::assertSentTo(
            $deps['client'],
            ReservationTemplateNotification::class,
            fn (ReservationTemplateNotification $notification): bool => $notification->key === EmailTemplateKey::ReservationChanged
                && ($notification->extraTokens['puvodni_termin'] ?? null) === $originalWhen
                && str_contains((string) ($notification->extraTokens['zprava'] ?? ''), 'Konec posouváme o hodinu.'),
        );
        Notification::assertSentTo(
            $deps['therapist']->user,
            TherapistReservationTemplateNotification::class,
            fn (TherapistReservationTemplateNotification $notification): bool => $notification->key === EmailTemplateKey::TherapistReservationChanged,
        );
    }

    public function test_editing_a_non_schedule_field_does_not_prompt_and_sends_nothing(): void
    {
        Notification::fake();

        $deps = $this->dependencies();
        $this->actingAs(User::factory()->admin()->create());

        $reservation = $this->makeReservation($deps);

        Livewire::test(EditReservation::class, ['record' => $reservation->getKey()])
            ->fillForm(['notes' => '<p>Interní poznámka.</p>'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertActionNotMounted('scheduleChangeNotification');

        Notification::assertNothingSent();
    }

    public function test_admin_can_view_reservation_detail(): void
    {
        $deps = $this->dependencies();
        $reservation = $this->makeReservation($deps, ['reservation_date' => now()->toDateString()]);

        $this->actingAs(User::factory()->admin()->create())
            ->get("/admin/provoz/reservations/{$reservation->getKey()}")
            ->assertSuccessful();
    }

    public function test_pending_doctor_note_is_surfaced_on_the_detail_and_filterable(): void
    {
        $deps = $this->dependencies();
        $this->actingAs(User::factory()->admin()->create());

        $awaiting = $this->makeReservation($deps, [
            'doctor_note_requested_at' => now(),
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
        ]);
        $plain = $this->makeReservation($deps, [
            'start_time' => '11:00:00',
            'end_time' => '12:00:00',
        ]);

        $this->get("/admin/provoz/reservations/{$awaiting->getKey()}")
            ->assertSuccessful()
            ->assertSee('Storno – potvrzení od lékaře');

        Livewire::test(ListReservations::class)
            ->assertCanSeeTableRecords([$awaiting, $plain])
            ->filterTable('doctor_note_pending', true)
            ->assertCanSeeTableRecords([$awaiting])
            ->assertCanNotSeeTableRecords([$plain]);
    }

    public function test_conflict_banner_is_shown_only_when_the_reservation_clashes(): void
    {
        $deps = $this->dependencies();
        $this->actingAs(User::factory()->admin()->create());

        // Two reservations sharing the room/therapist with overlapping times.
        $first = $this->makeReservation($deps, ['start_time' => '09:00:00', 'end_time' => '10:00:00']);
        $this->makeReservation($deps, ['start_time' => '09:30:00', 'end_time' => '10:30:00']);

        $this->get("/admin/provoz/reservations/{$first->getKey()}")
            ->assertSuccessful()
            ->assertSee('Konflikt v rozvrhu');

        // A lone reservation on another day has nothing to clash with.
        $lone = $this->makeReservation($deps, [
            'reservation_date' => now()->addDays(5)->toDateString(),
            'start_time' => '14:00:00',
            'end_time' => '15:00:00',
        ]);

        $this->get("/admin/provoz/reservations/{$lone->getKey()}")
            ->assertSuccessful()
            ->assertDontSee('Konflikt v rozvrhu');
    }

    public function test_admin_can_restore_a_trashed_reservation_from_the_table(): void
    {
        $deps = $this->dependencies();
        $this->actingAs(User::factory()->admin()->create());

        $reservation = $this->makeReservation($deps);
        $reservation->delete();

        Livewire::test(ListReservations::class)
            ->filterTable('trashed', true)
            ->callAction(TestAction::make('restoreReservation')->table($reservation), [
                'notify_client' => false,
            ]);

        $this->assertFalse($reservation->fresh()->trashed());
    }

    public function test_calendar_create_validates_required_fields(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ReservationCalendar::class)
            ->callAction('create', [
                'reservation_date' => null,
                'start_time' => null,
                'end_time' => null,
            ])
            ->assertHasActionErrors([
                'reservation_date' => 'required',
                'start_time' => 'required',
                'end_time' => 'required',
            ]);
    }

    public function test_calendar_create_with_notify_sends_notification(): void
    {
        Notification::fake();

        $deps = $this->dependencies();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ReservationCalendar::class)
            ->callAction('create', $this->formData($deps))
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('reservations', [
            'client_id' => $deps['client']->getKey(),
            'service_id' => $deps['service']->getKey(),
        ]);

        Notification::assertSentTo($deps['client'], ReservationNotification::class);
    }

    public function test_calendar_create_without_notify_sends_nothing(): void
    {
        Notification::fake();

        $deps = $this->dependencies();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ReservationCalendar::class)
            ->callAction('create', $this->formData($deps, ['notify_client' => false]))
            ->assertHasNoActionErrors();

        Notification::assertNothingSent();
    }

    public function test_calendar_edit_of_the_termin_prompts_then_sends_reservation_changed_with_original_values(): void
    {
        Notification::fake();
        $this->seed(SettingsSeeder::class);
        $this->seed(EmailTemplateSeeder::class);

        $deps = $this->dependencies();
        $this->actingAs(User::factory()->admin()->create());

        $reservation = $this->makeReservation($deps);
        $originalWhen = ReservationEmailContext::formatWhen($reservation);

        Livewire::test(ReservationCalendar::class)
            ->call('onEventClick', ['id' => (string) $reservation->getKey()])
            ->setActionData($this->formData($deps, ['end_time' => '11:00']))
            // Saving the edit chains straight into the notify prompt.
            ->callMountedAction()
            ->assertActionMounted('reservationChangeNotify')
            ->setActionData(['reason' => 'Konec posouváme.'])
            ->callMountedAction();

        Notification::assertSentTo(
            $deps['client'],
            ReservationTemplateNotification::class,
            function (ReservationTemplateNotification $notification) use ($originalWhen): bool {
                return $notification->key === EmailTemplateKey::ReservationChanged
                    && ($notification->extraTokens['puvodni_termin'] ?? null) === $originalWhen
                    && str_contains((string) ($notification->extraTokens['zprava'] ?? ''), 'Konec posouváme.');
            },
        );
    }

    public function test_calendar_edit_of_a_non_schedule_field_does_not_prompt(): void
    {
        Notification::fake();
        $this->seed(SettingsSeeder::class);
        $this->seed(EmailTemplateSeeder::class);

        $deps = $this->dependencies();
        $this->actingAs(User::factory()->admin()->create());

        $reservation = $this->makeReservation($deps);

        Livewire::test(ReservationCalendar::class)
            ->call('onEventClick', ['id' => (string) $reservation->getKey()])
            ->setActionData($this->formData($deps, ['notes' => '<p>Poznámka.</p>']))
            ->callMountedAction()
            ->assertActionNotMounted('reservationChangeNotify');

        Notification::assertNothingSent();
    }

    public function test_metrics_bar_is_shown_by_default_and_toggle_persists_per_user(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        // Shown by default.
        Livewire::test(ListReservations::class)
            ->assertSeeLivewire(ReservationStatsOverview::class)
            ->callAction('toggleStats')
            ->assertDontSeeLivewire(ReservationStatsOverview::class);

        // Preference persisted to the database.
        $this->assertFalse((bool) $admin->fresh()->getPreference('reservations.show_stats'));

        // A fresh mount for the same user starts hidden (cross-session persistence).
        Livewire::test(ListReservations::class)
            ->assertDontSeeLivewire(ReservationStatsOverview::class);
    }

    public function test_revenue_total_is_hidden_without_the_revenue_capability(): void
    {
        $deps = $this->dependencies();
        $this->makeReservation($deps);

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(ReservationStatsOverview::class)
            ->assertDontSee('Tržby')
            // Non-money metrics stay visible to everyone.
            ->assertSee('Nepotvrzeno');
    }

    public function test_revenue_total_is_shown_with_the_revenue_capability(): void
    {
        $deps = $this->dependencies();
        $this->makeReservation($deps);

        $this->actingAs(User::factory()->admin()->revenue()->create());
        Livewire::test(ReservationStatsOverview::class)
            ->assertSee('Tržby');
    }

    public function test_metric_cards_link_to_the_matching_table_filter(): void
    {
        $deps = $this->dependencies();
        $this->makeReservation($deps);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ReservationStatsOverview::class)
            ->assertSee('Zobrazit')
            ->assertSee('filters%5Bstatus%5D%5Bvalue%5D=pending', escape: false)
            ->assertSee('filters%5Boutstanding%5D%5BisActive%5D=1', escape: false);
    }

    public function test_outstanding_and_unsettled_filters_narrow_the_table(): void
    {
        $deps = $this->dependencies();
        $this->actingAs(User::factory()->admin()->create());

        $unpaid = $this->makeReservation($deps, [
            'payment_status' => PaymentStatus::Unpaid,
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);
        $paid = $this->makeReservation($deps, [
            'payment_status' => PaymentStatus::Paid,
            'start_time' => '11:00',
            'end_time' => '12:00',
        ]);

        Livewire::test(ListReservations::class)
            ->filterTable('outstanding')
            ->assertCanSeeTableRecords([$unpaid])
            ->assertCanNotSeeTableRecords([$paid]);

        $pastUnsettled = $this->makeReservation($deps, [
            'reservation_date' => now()->subDay()->toDateString(),
            'status' => ReservationStatus::Confirmed,
        ]);

        Livewire::test(ListReservations::class)
            ->filterTable('unsettled_past')
            ->assertCanSeeTableRecords([$pastUnsettled])
            ->assertCanNotSeeTableRecords([$unpaid, $paid]);
    }
}
