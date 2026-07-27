<?php

namespace Tests\Feature\Reservations;

use App\Enums\EmailTemplateKey;
use App\Enums\ReservationStatus;
use App\Enums\ServiceType;
use App\Enums\ServiceVisibility;
use App\Models\Reservation;
use App\Models\ReservationDayWaitlistEntry;
use App\Models\Room;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\StaffProfile;
use App\Models\TherapistWorkBlock;
use App\Models\User;
use App\Notifications\ReservationDayWaitlistNotification;
use App\Support\Reservations\NotifyReservationDayWaitlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DayWaitlistNotifyTest extends TestCase
{
    use RefreshDatabase;

    private string $date;

    private Room $room;

    private Service $service;

    private StaffProfile $therapist;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->date = Carbon::today()->addWeeks(8)->startOfWeek(Carbon::MONDAY)->toDateString();
        $this->room = Room::factory()->create();

        $category = ServiceCategory::factory()->create(['type' => ServiceType::Massage]);
        $this->service = Service::factory()->create([
            'category_id' => $category->id,
            'duration_minutes' => 60,
            'visibility' => ServiceVisibility::Public,
            'published_at' => now(),
        ]);
        $this->therapist = StaffProfile::factory()->create(['published_at' => now()]);
        $this->service->therapists()->attach($this->therapist);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function schedule(StaffProfile $therapist, string $date, string $start = '08:00', string $end = '09:00'): void
    {
        TherapistWorkBlock::factory()->create([
            'therapist_id' => $therapist->id,
            'room_id' => $this->room->id,
            'work_date' => $date,
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }

    private function book(StaffProfile $therapist, string $date, string $start = '08:00', string $end = '09:00'): Reservation
    {
        return Reservation::factory()->confirmed()->create([
            'service_id' => $this->service->id,
            'therapist_id' => $therapist->id,
            'room_id' => $this->room->id,
            'client_id' => User::factory()->customer(),
            'reservation_date' => $date,
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }

    private function waiter(?string $therapistId, string $date, ?Service $service = null): User
    {
        $client = User::factory()->customer()->create();

        ReservationDayWaitlistEntry::factory()->forClient($client)->create([
            'therapist_id' => $therapistId,
            'service_id' => $service?->id,
            'reservation_date' => $date,
        ]);

        return $client;
    }

    public function test_cancelling_emails_every_pending_waiter_for_that_day(): void
    {
        $this->schedule($this->therapist, $this->date);
        $reservation = $this->book($this->therapist, $this->date);

        $waiterA = $this->waiter($this->therapist->id, $this->date);
        $waiterB = $this->waiter($this->therapist->id, $this->date);

        $reservation->update(['status' => ReservationStatus::Cancelled]);

        foreach ([$waiterA, $waiterB] as $waiter) {
            Notification::assertSentTo(
                $waiter,
                ReservationDayWaitlistNotification::class,
                fn (ReservationDayWaitlistNotification $n): bool => $n->key === EmailTemplateKey::ReservationDayWaitlistSpotAvailable
                    && str_contains($n->tokens['odkaz'] ?? '', 'datum='.$this->date),
            );
        }

        $this->assertSame(0, ReservationDayWaitlistEntry::query()->whereNull('notified_at')->count());
    }

    public function test_a_waiter_for_another_therapist_is_not_notified(): void
    {
        $this->schedule($this->therapist, $this->date);
        $reservation = $this->book($this->therapist, $this->date);

        $otherTherapist = StaffProfile::factory()->create();
        $outsider = $this->waiter($otherTherapist->id, $this->date);

        $reservation->update(['status' => ReservationStatus::Cancelled]);

        Notification::assertNotSentTo($outsider, ReservationDayWaitlistNotification::class);
    }

    public function test_an_any_therapist_waiter_is_notified_when_any_therapist_frees(): void
    {
        $this->schedule($this->therapist, $this->date);
        $reservation = $this->book($this->therapist, $this->date);

        $anyWaiter = $this->waiter(null, $this->date);

        $reservation->update(['status' => ReservationStatus::Cancelled]);

        Notification::assertSentTo($anyWaiter, ReservationDayWaitlistNotification::class);
    }

    public function test_matching_is_service_agnostic(): void
    {
        $this->schedule($this->therapist, $this->date);
        $reservation = $this->book($this->therapist, $this->date);

        // The waiter was browsing a different service than the freed booking.
        $otherService = Service::factory()->create(['duration_minutes' => 30]);
        $waiter = $this->waiter($this->therapist->id, $this->date, $otherService);

        $reservation->update(['status' => ReservationStatus::Cancelled]);

        Notification::assertSentTo($waiter, ReservationDayWaitlistNotification::class);
    }

    public function test_an_already_notified_waiter_is_not_re_emailed(): void
    {
        $this->schedule($this->therapist, $this->date);
        $reservation = $this->book($this->therapist, $this->date);

        $client = User::factory()->customer()->create();
        ReservationDayWaitlistEntry::factory()->forClient($client)->notified()->create([
            'therapist_id' => $this->therapist->id,
            'reservation_date' => $this->date,
        ]);

        $reservation->update(['status' => ReservationStatus::Cancelled]);

        Notification::assertNotSentTo($client, ReservationDayWaitlistNotification::class);
    }

    public function test_no_email_when_the_day_has_no_opening(): void
    {
        // Day stays fully booked (reservation still active) — calling the notifier
        // directly must find no opening and e-mail nobody.
        $this->schedule($this->therapist, $this->date);
        $this->book($this->therapist, $this->date);
        $waiter = $this->waiter($this->therapist->id, $this->date);

        app(NotifyReservationDayWaitlist::class)($this->therapist->id, $this->date);

        Notification::assertNotSentTo($waiter, ReservationDayWaitlistNotification::class);
    }

    public function test_a_reschedule_frees_the_original_day(): void
    {
        $this->schedule($this->therapist, $this->date);
        $reservation = $this->book($this->therapist, $this->date);
        $waiter = $this->waiter($this->therapist->id, $this->date);

        // Move the reservation to another day — the original day frees up.
        $reservation->update(['reservation_date' => Carbon::parse($this->date)->addDay()->toDateString()]);

        Notification::assertSentTo($waiter, ReservationDayWaitlistNotification::class);
    }

    public function test_a_soft_delete_frees_the_day(): void
    {
        $this->schedule($this->therapist, $this->date);
        $reservation = $this->book($this->therapist, $this->date);
        $waiter = $this->waiter($this->therapist->id, $this->date);

        $reservation->delete();

        Notification::assertSentTo($waiter, ReservationDayWaitlistNotification::class);
    }

    public function test_booking_clears_the_bookers_own_pending_entry(): void
    {
        $this->schedule($this->therapist, $this->date);

        $client = User::factory()->customer()->create();
        ReservationDayWaitlistEntry::factory()->forClient($client)->create([
            'therapist_id' => $this->therapist->id,
            'reservation_date' => $this->date,
        ]);

        Reservation::factory()->confirmed()->create([
            'service_id' => $this->service->id,
            'therapist_id' => $this->therapist->id,
            'room_id' => $this->room->id,
            'client_id' => $client->id,
            'reservation_date' => $this->date,
            'start_time' => '08:00',
            'end_time' => '09:00',
        ]);

        $this->assertSame(0, ReservationDayWaitlistEntry::query()->where('client_id', $client->id)->count());
    }
}
