<?php

namespace Tests\Feature\Zone;

use App\Enums\EmailTemplateKey;
use App\Enums\ReservationStatus;
use App\Enums\ServiceType;
use App\Enums\ServiceVisibility;
use App\Livewire\Zone\RescheduleReservation as RescheduleComponent;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\TherapistProfile;
use App\Models\TherapistWorkBlock;
use App\Models\User;
use App\Notifications\ReservationTemplateNotification;
use App\Notifications\TherapistReservationTemplateNotification;
use App\Support\Reservations\RescheduleReservation;
use App\Support\Reservations\SlotTakenException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class RescheduleReservationTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $date;

    private Room $room;

    private Service $service;

    private TherapistProfile $therapist;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->date = Carbon::today()->addWeeks(6)->startOfWeek(Carbon::MONDAY);
        $this->room = Room::factory()->create();

        $category = ServiceCategory::factory()->create(['type' => ServiceType::Massage]);
        $this->service = Service::factory()->create([
            'category_id' => $category->id,
            'duration_minutes' => 60,
            'break_minutes' => 15,
            'visibility' => ServiceVisibility::Public,
            'published_at' => now(),
        ]);

        $this->therapist = TherapistProfile::factory()->create(['published_at' => now()]);
        $this->service->therapists()->attach($this->therapist);

        TherapistWorkBlock::factory()->create([
            'therapist_id' => $this->therapist->id,
            'room_id' => $this->room->id,
            'work_date' => $this->date->toDateString(),
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);

        $this->client = User::factory()->customer()->create();
    }

    private function reservation(int $daysAhead = 20): Reservation
    {
        return Reservation::factory()->create([
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'therapist_id' => $this->therapist->id,
            'room_id' => $this->room->id,
            'reservation_date' => today()->addDays($daysAhead)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'status' => ReservationStatus::Confirmed,
        ]);
    }

    public function test_moving_updates_the_reservation_and_emails_both_sides_with_the_original_termin(): void
    {
        $reservation = $this->reservation();
        $original = $reservation->startsAt()->translatedFormat('j. F Y');

        app(RescheduleReservation::class)($reservation, $this->date->toDateString(), '10:00');

        $reservation->refresh();

        $this->assertSame($this->date->toDateString(), $reservation->reservation_date->toDateString());
        $this->assertSame('10:00:00', $reservation->start_time);
        // Status is untouched by a move.
        $this->assertSame(ReservationStatus::Confirmed, $reservation->status);

        Notification::assertSentTo($this->client, ReservationTemplateNotification::class, fn (ReservationTemplateNotification $notification): bool => $notification->key === EmailTemplateKey::ReservationChanged
            && str_contains($notification->extraTokens['puvodni_termin'] ?? '', $original));

        Notification::assertSentTo($this->therapist->user, TherapistReservationTemplateNotification::class, fn (TherapistReservationTemplateNotification $notification): bool => $notification->key === EmailTemplateKey::TherapistReservationChanged);
    }

    public function test_a_taken_slot_is_rejected(): void
    {
        $reservation = $this->reservation();

        // Someone else already holds 10:00 with this therapist.
        Reservation::factory()->create([
            'service_id' => $this->service->id,
            'therapist_id' => $this->therapist->id,
            'room_id' => $this->room->id,
            'reservation_date' => $this->date->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'status' => ReservationStatus::Confirmed,
        ]);

        $this->expectException(SlotTakenException::class);

        app(RescheduleReservation::class)($reservation, $this->date->toDateString(), '10:00');
    }

    public function test_the_page_offers_slots_and_moves_the_reservation(): void
    {
        $reservation = $this->reservation();

        Livewire::actingAs($this->client)
            ->test(RescheduleComponent::class, ['reservation' => $reservation])
            ->assertSee('Vyberte čas')
            ->call('selectDate', $this->date->toDateString())
            ->call('selectTime', '10:00')
            ->call('reschedule')
            ->assertRedirect(route('zone.reservations.show', $reservation));

        $this->assertSame($this->date->toDateString(), $reservation->fresh()->reservation_date->toDateString());
    }

    public function test_a_reservation_inside_the_storno_window_cannot_be_moved(): void
    {
        // Default cancel window is 24h — a reservation later today is inside it.
        $reservation = Reservation::factory()->create([
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'therapist_id' => $this->therapist->id,
            'room_id' => $this->room->id,
            'reservation_date' => today()->toDateString(),
            'start_time' => now()->addHours(3)->format('H:i:s'),
            'end_time' => now()->addHours(4)->format('H:i:s'),
            'status' => ReservationStatus::Confirmed,
        ]);

        Livewire::actingAs($this->client)
            ->test(RescheduleComponent::class, ['reservation' => $reservation])
            ->assertSee('Termín už nelze přesunout')
            ->call('selectDate', $this->date->toDateString())
            ->call('selectTime', '10:00')
            ->call('reschedule');

        // Guarded: nothing moved.
        $this->assertSame(today()->toDateString(), $reservation->fresh()->reservation_date->toDateString());
    }

    public function test_another_clients_reservation_is_not_reachable(): void
    {
        $reservation = $this->reservation();
        $stranger = User::factory()->customer()->create(['email_verified_at' => now()]);

        $this->actingAs($stranger)
            ->get(route('zone.reservations.reschedule', $reservation))
            ->assertNotFound();
    }
}
