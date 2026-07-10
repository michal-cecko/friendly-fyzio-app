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

class ReservationAutoCancelTest extends TestCase
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
     * A reservation booked with the full confirmation lead time (created well before the
     * confirmation window opened).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function reservation(array $attributes = [], ?string $createdAt = '2026-07-01 09:00:00'): Reservation
    {
        $reservation = Reservation::factory()->create(array_merge([
            'client_id' => User::factory()->customer(),
            'status' => ReservationStatus::Pending,
        ], $attributes));

        if ($createdAt !== null) {
            $reservation->forceFill(['created_at' => $createdAt])->saveQuietly();
        }

        return $reservation->fresh();
    }

    public function test_unconfirmed_reservation_past_the_cutoff_is_cancelled_and_emailed(): void
    {
        Notification::fake();

        $reservation = $this->reservation(['reservation_date' => '2026-07-09', 'start_time' => '08:00', 'end_time' => '09:00']);

        $this->artisan('reservations:cancel-unconfirmed')->assertSuccessful();

        $reservation->refresh();
        $this->assertSame(ReservationStatus::Cancelled, $reservation->status);
        $this->assertSame('Automatické zrušení – nepotvrzená účast', $reservation->cancellation_reason);

        Notification::assertSentTo($reservation->client, ReservationTemplateNotification::class, function (ReservationTemplateNotification $n): bool {
            return $n->key === EmailTemplateKey::ReservationAutoCancelled;
        });
    }

    public function test_last_minute_booking_is_not_auto_cancelled(): void
    {
        Notification::fake();

        // Created "now" (inside the 48h confirmation window) for a visit 23h away.
        $reservation = $this->reservation(
            ['reservation_date' => '2026-07-09', 'start_time' => '08:00', 'end_time' => '09:00'],
            createdAt: null,
        );

        $this->artisan('reservations:cancel-unconfirmed')->assertSuccessful();

        $this->assertSame(ReservationStatus::Pending, $reservation->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_confirmed_and_out_of_window_reservations_are_left_alone(): void
    {
        Notification::fake();

        $confirmed = $this->reservation(['reservation_date' => '2026-07-09', 'start_time' => '08:00', 'end_time' => '09:00', 'status' => ReservationStatus::Confirmed]);
        $outside = $this->reservation(['reservation_date' => '2026-07-09', 'start_time' => '12:00', 'end_time' => '13:00']);

        $this->artisan('reservations:cancel-unconfirmed')->assertSuccessful();

        $this->assertSame(ReservationStatus::Confirmed, $confirmed->fresh()->status);
        $this->assertSame(ReservationStatus::Pending, $outside->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_auto_cancel_is_idempotent(): void
    {
        Notification::fake();

        $reservation = $this->reservation(['reservation_date' => '2026-07-09', 'start_time' => '08:00', 'end_time' => '09:00']);

        $this->artisan('reservations:cancel-unconfirmed')->assertSuccessful();
        $this->artisan('reservations:cancel-unconfirmed')->assertSuccessful();

        Notification::assertSentToTimes($reservation->client, ReservationTemplateNotification::class, 1);
    }
}
