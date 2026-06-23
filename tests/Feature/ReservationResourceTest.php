<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Filament\Clusters\Provoz\Resources\Reservations\Pages\CreateReservation;
use App\Models\Building;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Service;
use App\Models\TherapistProfile;
use App\Models\User;
use App\Notifications\ReservationNotification;
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

    public function test_admin_can_view_reservations_list(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/provoz/reservations')
            ->assertSuccessful();
    }

    public function test_admin_can_view_reservation_detail(): void
    {
        $deps = $this->dependencies();

        $reservation = Reservation::create([
            'client_id' => $deps['client']->getKey(),
            'service_id' => $deps['service']->getKey(),
            'therapist_id' => $deps['therapist']->getKey(),
            'room_id' => $deps['room']->getKey(),
            'reservation_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => ReservationStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->get("/admin/provoz/reservations/{$reservation->getKey()}")
            ->assertSuccessful();
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateReservation::class)
            ->fillForm([
                'reservation_date' => null,
                'start_time' => null,
                'end_time' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'reservation_date' => 'required',
                'start_time' => 'required',
                'end_time' => 'required',
            ]);
    }

    public function test_create_reservation_with_notify_sends_notification(): void
    {
        Notification::fake();

        $deps = $this->dependencies();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateReservation::class)
            ->fillForm([
                'client_id' => $deps['client']->getKey(),
                'service_id' => $deps['service']->getKey(),
                'therapist_id' => $deps['therapist']->getKey(),
                'room_id' => $deps['room']->getKey(),
                'reservation_date' => now()->addDay()->toDateString(),
                'start_time' => '09:00',
                'end_time' => '10:00',
                'status' => 'confirmed',
                'payment_status' => 'unpaid',
                'notify_client' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('reservations', [
            'client_id' => $deps['client']->getKey(),
            'service_id' => $deps['service']->getKey(),
        ]);

        Notification::assertSentTo($deps['client'], ReservationNotification::class);
    }
}
