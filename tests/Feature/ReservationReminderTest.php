<?php

namespace Tests\Feature;

use App\Enums\EmailTemplateKey;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\ReservationTemplateNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReservationReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-08 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function reservation(array $attributes = []): Reservation
    {
        return Reservation::factory()->create(array_merge([
            'client_id' => User::factory()->customer(),
            'status' => ReservationStatus::Confirmed,
            'reminder_sent_at' => null,
        ], $attributes));
    }

    public function test_only_confirmed_reservations_in_the_window_get_a_reminder(): void
    {
        Notification::fake();

        // 23h out, confirmed, not yet reminded -> reminded.
        $inWindow = $this->reservation(['reservation_date' => '2026-07-09', 'start_time' => '08:00', 'end_time' => '09:00']);
        // Same window but still pending -> not reminded (confirmed-only).
        $pending = $this->reservation(['reservation_date' => '2026-07-09', 'start_time' => '08:30', 'end_time' => '09:30', 'status' => ReservationStatus::Pending]);
        // Beyond the 24h window -> skipped.
        $outside = $this->reservation(['reservation_date' => '2026-07-09', 'start_time' => '12:00', 'end_time' => '13:00']);
        // In the past -> skipped.
        $past = $this->reservation(['reservation_date' => '2026-07-08', 'start_time' => '07:00', 'end_time' => '08:00']);
        // Already reminded -> skipped.
        $alreadySent = $this->reservation(['reservation_date' => '2026-07-09', 'start_time' => '08:00', 'end_time' => '09:00', 'reminder_sent_at' => now()]);

        $this->artisan('reservations:send-reminders')->assertSuccessful();

        Notification::assertSentTo($inWindow->client, ReservationTemplateNotification::class, function (ReservationTemplateNotification $n): bool {
            return $n->key === EmailTemplateKey::ReservationReminder;
        });
        Notification::assertNotSentTo($pending->client, ReservationTemplateNotification::class);
        Notification::assertNotSentTo($outside->client, ReservationTemplateNotification::class);
        Notification::assertNotSentTo($past->client, ReservationTemplateNotification::class);
        Notification::assertNotSentTo($alreadySent->client, ReservationTemplateNotification::class);

        $this->assertNotNull($inWindow->fresh()->reminder_sent_at);
    }

    public function test_reminder_sends_once(): void
    {
        Notification::fake();

        $reservation = $this->reservation(['reservation_date' => '2026-07-09', 'start_time' => '08:00', 'end_time' => '09:00']);

        $this->artisan('reservations:send-reminders')->assertSuccessful();
        $this->artisan('reservations:send-reminders')->assertSuccessful();

        Notification::assertSentToTimes($reservation->client, ReservationTemplateNotification::class, 1);
    }
}
