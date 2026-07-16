<?php

namespace Tests\Feature\Emails;

use App\Enums\EmailTemplateKey;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\TherapistProfile;
use App\Models\User;
use App\Notifications\ReservationNotification;
use App\Notifications\TherapistReservationTemplateNotification;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TherapistReservationTemplatesTest extends TestCase
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

    private function reservationWithTherapist(array $attributes = []): Reservation
    {
        $therapist = TherapistProfile::factory()->create();
        $therapist->user->update(['name' => 'Petra Nováková']);

        $client = User::factory()->customer()->create([
            'name' => 'Jana Kováčová',
            'phone' => '+420604123456',
            'email' => 'jana.kovacova@example.cz',
        ]);

        return Reservation::factory()->create(array_merge([
            'therapist_id' => $therapist->id,
            'client_id' => $client->id,
            'service_id' => Service::factory()->create(['name' => 'Sportovní masáž (60 min)']),
            'status' => ReservationStatus::Pending,
            'reservation_date' => '2026-07-20',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ], $attributes));
    }

    private function render(Reservation $reservation, EmailTemplateKey $key, array $extra = []): string
    {
        $notification = new TherapistReservationTemplateNotification($reservation, $key, $extra);

        return $notification->toMail($reservation->therapist->user)->viewData['html'] ?? '';
    }

    public function test_created_template_renders_client_details_and_confirm_link(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        $reservation = $this->reservationWithTherapist();

        $html = $this->render($reservation, EmailTemplateKey::TherapistReservationCreated, [
            'odkaz_potvrdit' => 'https://example.test/confirm',
        ]);

        $this->assertStringContainsString('Petra', $html);          // therapist greeting
        $this->assertStringContainsString('Jana Kováčová', $html);  // client
        $this->assertStringContainsString('jana.kovacova@example.cz', $html);
        $this->assertStringContainsString('Sportovní masáž (60 min)', $html);
        $this->assertStringContainsString('https://example.test/confirm', $html);
    }

    public function test_cancelled_template_renders_storno_resolution(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        $reservation = $this->reservationWithTherapist();

        $html = $this->render($reservation, EmailTemplateKey::TherapistReservationCancelled, [
            'storno_reseni' => 'Klient uhradí storno poplatek',
            'storno_castka' => '600 Kč',
        ]);

        $this->assertStringContainsString('Klient uhradí storno poplatek', $html);
        $this->assertStringContainsString('600 Kč', $html);
    }

    public function test_changed_template_renders_original_values(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        $reservation = $this->reservationWithTherapist();

        $html = $this->render($reservation, EmailTemplateKey::TherapistReservationChanged, [
            'puvodni_sluzba' => 'Klasická masáž (30 min)',
            'puvodni_termin' => '18. července 2026, 09:00',
        ]);

        $this->assertStringContainsString('Klasická masáž (30 min)', $html);
        $this->assertStringContainsString('18. července 2026, 09:00', $html);
    }

    public function test_confirmed_template_renders(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        $reservation = $this->reservationWithTherapist();

        $html = $this->render($reservation, EmailTemplateKey::TherapistReservationConfirmed);

        $this->assertStringContainsString('Jana Kováčová', $html);
        $this->assertStringNotContainsString('{{ klient }}', $html);
    }

    public function test_client_confirming_via_manage_link_notifies_the_therapist(): void
    {
        Notification::fake();

        $reservation = $this->reservationWithTherapist();

        $this->post($reservation->manageUrl(), ['action' => 'confirm'])->assertRedirect();

        Notification::assertSentTo(
            $reservation->therapist->user,
            TherapistReservationTemplateNotification::class,
            fn (TherapistReservationTemplateNotification $n): bool => $n->key === EmailTemplateKey::TherapistReservationConfirmed,
        );
    }

    public function test_client_cancelling_via_manage_link_notifies_the_therapist(): void
    {
        Notification::fake();

        // Pending + outside the storno window → free cancel path.
        $reservation = $this->reservationWithTherapist();

        $this->post($reservation->manageUrl(), ['action' => 'cancel'])->assertRedirect();

        Notification::assertSentTo(
            $reservation->therapist->user,
            TherapistReservationTemplateNotification::class,
            fn (TherapistReservationTemplateNotification $n): bool => $n->key === EmailTemplateKey::TherapistReservationCancelled,
        );

        // The legacy therapist mail must no longer fire.
        Notification::assertNotSentTo($reservation->therapist->user, ReservationNotification::class);
    }
}
