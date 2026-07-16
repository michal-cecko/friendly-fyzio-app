<?php

namespace Tests\Feature\Emails;

use App\Enums\EmailTemplateKey;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\ReservationTemplateNotification;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingEmailTemplatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_booking_templates_seed_and_render(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        foreach ([
            EmailTemplateKey::ReservationCreated,
            EmailTemplateKey::ReservationAutoConfirmed,
        ] as $key) {
            $this->assertDatabaseHas('email_templates', ['key' => $key->value]);
        }

        $client = User::factory()->customer()->create(['name' => 'Jana Nováková']);
        $reservation = Reservation::factory()->create(['client_id' => $client->id]);

        foreach ([
            EmailTemplateKey::ReservationCreated,
            EmailTemplateKey::ReservationAutoConfirmed,
        ] as $key) {
            $html = (new ReservationTemplateNotification($reservation, $key))->toMail($client)->viewData['html'] ?? '';

            $this->assertStringContainsString('Jana', $html);
            // The manage magic link is rendered into the button (its query "&" is
            // HTML-escaped in the href, so match the stable path fragment).
            $this->assertStringContainsString('rezervace/spravovat/'.$reservation->getKey(), $html);
            $this->assertStringNotContainsString('{{', $html);
        }
    }
}
