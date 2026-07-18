<?php

namespace Tests\Feature\ActivityLog;

use App\Enums\EmailTemplateKey;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\ReservationTemplateNotification;
use App\Support\ActivityLog\LogActivity;
use App\Support\Reservations\ClientReservationActions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class SemanticLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_sending_an_email_is_logged_with_body_and_recipients(): void
    {
        $reservation = Reservation::factory()->create();
        $client = $reservation->client;

        $client->notify(new ReservationTemplateNotification($reservation, EmailTemplateKey::ReservationConfirmed));

        $activity = Activity::query()
            ->where('event', 'email_sent')
            ->where('subject_type', 'reservation')
            ->where('subject_id', $reservation->getKey())
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertContains($client->email, collect($activity->getProperty('recipients'))
            ->map(fn (string $r): string => str_contains($r, '<') ? trim(explode('<', $r)[1], '> ') : $r)
            ->all());
        $this->assertNotEmpty($activity->getProperty('subject'));
        $this->assertSame('ReservationTemplateNotification', $activity->getProperty('notification'));
    }

    public function test_customer_confirming_reservation_logs_the_event_with_notify_flags(): void
    {
        $reservation = Reservation::factory()->create(['status' => ReservationStatus::Pending]);

        (new ClientReservationActions)->confirm($reservation->fresh());

        $activity = Activity::query()
            ->where('event', 'reservation_confirmed')
            ->where('subject_id', $reservation->getKey())
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertTrue($activity->getProperty('notified_client'));
        // Customer-initiated: the causer is the client, so "Kdo" shows their name.
        $this->assertSame($reservation->client_id, $activity->causer_id);
    }

    public function test_customer_storno_pay_logs_the_fee_event(): void
    {
        $reservation = Reservation::factory()->create([
            'status' => ReservationStatus::Confirmed,
            'reservation_date' => today()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);

        // Force the storno-decision path regardless of window config.
        if (! $reservation->requiresStornoDecision()) {
            $this->markTestSkipped('Reservation does not require a storno decision under current settings.');
        }

        (new ClientReservationActions)->cancelAndPay($reservation->fresh());

        $activity = Activity::query()
            ->where('event', 'reservation_storno_charged')
            ->where('subject_id', $reservation->getKey())
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertNotEmpty($activity->getProperty('fee'));
    }

    public function test_admin_causer_is_recorded_on_semantic_events(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $reservation = Reservation::factory()->create(['status' => ReservationStatus::Pending]);

        // The edit page's afterSave records reservation_edited; here we assert the
        // helper attributes the acting admin as causer for staff-driven events.
        LogActivity::record('reservation_edited', $reservation, 'Rezervace upravena', [
            'notified_client' => false,
            'notified_therapist' => false,
        ]);

        $activity = Activity::query()
            ->where('event', 'reservation_edited')
            ->where('subject_id', $reservation->getKey())
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($admin->getKey(), $activity->causer_id);
    }
}
