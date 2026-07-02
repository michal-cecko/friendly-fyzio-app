<?php

namespace Tests\Feature;

use App\Enums\DayOfWeek;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\ServiceType;
use App\Enums\ServiceVisibility;
use App\Enums\UserRole;
use App\Enums\WeekType;
use App\Jobs\SubscribeToNewsletterJob;
use App\Models\ClientProfile;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\TherapistProfile;
use App\Models\TherapistWeeklySchedule;
use App\Models\User;
use App\Notifications\ClientAccountCreatedNotification;
use App\Notifications\ReservationNotification;
use App\Support\Reservations\CreateReservationFromWizard;
use App\Support\Reservations\ReservationBookingData;
use App\Support\Reservations\SlotTakenException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CreateReservationFromWizardTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $date;

    private Room $room;

    private Service $service;

    private TherapistProfile $therapist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->date = Carbon::today()->addWeeks(8)->startOfWeek(Carbon::MONDAY);
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

        TherapistWeeklySchedule::factory()->create([
            'therapist_id' => $this->therapist->id,
            'room_id' => $this->room->id,
            'day_of_week' => DayOfWeek::fromCarbon($this->date),
            'week_type' => WeekType::All,
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);
    }

    private function data(array $overrides = []): ReservationBookingData
    {
        return new ReservationBookingData(...array_merge([
            'service' => $this->service,
            'therapistId' => $this->therapist->id,
            'date' => $this->date->toDateString(),
            'startTime' => '08:00',
            'firstName' => 'Jana',
            'lastName' => 'Nováková',
            'email' => 'jana@example.com',
            'phone' => '+420604793255',
            'note' => 'Bolesti zad',
            'newsletter' => true,
        ], $overrides));
    }

    private function action(): CreateReservationFromWizard
    {
        return app(CreateReservationFromWizard::class);
    }

    public function test_creates_reservation_and_account_for_anonymous_client(): void
    {
        Notification::fake();

        $reservation = ($this->action())($this->data());

        $this->assertDatabaseHas(Reservation::class, [
            'id' => $reservation->id,
            'service_id' => $this->service->id,
            'therapist_id' => $this->therapist->id,
            'room_id' => $this->room->id,
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'status' => ReservationStatus::Pending->value,
            'payment_status' => PaymentStatus::Unpaid->value,
            'notes' => 'Bolesti zad',
        ]);
        $this->assertSame($this->date->toDateString(), $reservation->reservation_date->toDateString());

        $client = User::where('email', 'jana@example.com')->sole();
        $this->assertSame(UserRole::Customer, $client->role);
        $this->assertNotNull($client->newsletter_opted_in_at);
        $this->assertDatabaseHas(ClientProfile::class, ['user_id' => $client->id]);

        Notification::assertSentTo($client, ReservationNotification::class);
        Notification::assertSentTo($client, ClientAccountCreatedNotification::class);
        Notification::assertSentTo($this->therapist->user, ReservationNotification::class);
    }

    public function test_reuses_existing_account_by_email_without_welcome(): void
    {
        Notification::fake();
        $existing = User::factory()->customer()->create(['email' => 'jana@example.com']);

        ($this->action())($this->data());

        $this->assertSame(1, User::where('email', 'jana@example.com')->count());
        $this->assertSame($existing->id, Reservation::sole()->client_id);
        Notification::assertNotSentTo($existing, ClientAccountCreatedNotification::class);
    }

    public function test_records_newsletter_opt_in_for_existing_client(): void
    {
        Notification::fake();
        $existing = User::factory()->customer()->create([
            'email' => 'jana@example.com',
            'newsletter_opted_in_at' => null,
        ]);

        ($this->action())($this->data(['newsletter' => true]));

        $this->assertNotNull($existing->fresh()->newsletter_opted_in_at);
    }

    public function test_dispatches_newsletter_subscribe_when_opted_in(): void
    {
        Notification::fake();
        Queue::fake();

        ($this->action())($this->data(['newsletter' => true]));

        Queue::assertPushed(
            SubscribeToNewsletterJob::class,
            fn (SubscribeToNewsletterJob $job): bool => $job->email === 'jana@example.com',
        );
    }

    public function test_does_not_dispatch_newsletter_subscribe_when_not_opted_in(): void
    {
        Notification::fake();
        Queue::fake();

        ($this->action())($this->data(['newsletter' => false]));

        Queue::assertNotPushed(SubscribeToNewsletterJob::class);
    }

    public function test_uses_authenticated_client(): void
    {
        Notification::fake();
        $client = User::factory()->customer()->create();

        ($this->action())($this->data(['client' => $client, 'email' => 'someone-else@example.com']));

        $this->assertSame($client->id, Reservation::sole()->client_id);
        $this->assertDatabaseMissing(User::class, ['email' => 'someone-else@example.com']);
    }

    public function test_rejects_a_slot_that_is_not_offerable(): void
    {
        $this->expectException(SlotTakenException::class);

        // 08:30 is never offered on an empty day (the 09:15 cadence rule).
        ($this->action())($this->data(['startTime' => '08:30']));
    }

    public function test_prevents_double_booking_the_same_slot(): void
    {
        ($this->action())($this->data(['email' => 'first@example.com']));

        $this->expectException(SlotTakenException::class);
        ($this->action())($this->data(['email' => 'second@example.com']));
    }
}
