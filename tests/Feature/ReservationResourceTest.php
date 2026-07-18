<?php

namespace Tests\Feature;

use App\Enums\EmailTemplateKey;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Filament\Clusters\Provoz\Resources\Reservations\Pages\ListReservations;
use App\Filament\Widgets\ReservationCalendar;
use App\Models\Building;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Service;
use App\Models\TherapistProfile;
use App\Models\User;
use App\Notifications\ReservationNotification;
use App\Notifications\ReservationTemplateNotification;
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
     * @return array{client: User, service: Service, therapist: TherapistProfile, room: Room}
     */
    protected function dependencies(): array
    {
        $building = Building::create(['name' => 'Budova', 'address' => 'Adresa']);
        $room = Room::create(['building_id' => $building->getKey(), 'name' => 'Sál']);
        $therapist = TherapistProfile::create(['user_id' => User::factory()->therapist()->create()->getKey()]);

        return [
            'client' => User::factory()->customer()->create(),
            'service' => Service::factory()->create(),
            'therapist' => $therapist,
            'room' => $room,
        ];
    }

    /**
     * @param  array{client: User, service: Service, therapist: TherapistProfile, room: Room}  $deps
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
     * @param  array{client: User, service: Service, therapist: TherapistProfile, room: Room}  $deps
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

    public function test_calendar_edit_with_notify_sends_reservation_changed_with_original_values(): void
    {
        Notification::fake();
        $this->seed(SettingsSeeder::class);
        $this->seed(EmailTemplateSeeder::class);

        $deps = $this->dependencies();
        $this->actingAs(User::factory()->admin()->create());

        $reservation = $this->makeReservation($deps);
        $originalServiceName = $deps['service']->name;

        $newService = Service::factory()->create();

        Livewire::test(ReservationCalendar::class)
            ->call('onEventClick', ['id' => (string) $reservation->getKey()])
            ->setActionData($this->formData($deps, ['service_id' => $newService->getKey()]))
            ->callMountedAction();

        Notification::assertSentTo(
            $deps['client'],
            ReservationTemplateNotification::class,
            function (ReservationTemplateNotification $notification) use ($originalServiceName): bool {
                return $notification->key === EmailTemplateKey::ReservationChanged
                    && ($notification->extraTokens['puvodni_sluzba'] ?? null) === $originalServiceName;
            },
        );
    }
}
