<?php

namespace Tests\Feature\Emails;

use App\Enums\EmailTemplateKey;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\StaffProfile;
use App\Notifications\ReservationTemplateNotification;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationReplyToTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EmailTemplateSeeder::class);
    }

    public function test_client_email_reply_to_points_to_the_assigned_therapist(): void
    {
        $therapist = StaffProfile::factory()->create();
        $therapist->user->update(['name' => 'Petra Nováková', 'email' => 'petra@friendlyfyzio.cz']);

        $reservation = Reservation::factory()->create([
            'therapist_id' => $therapist->id,
            'status' => ReservationStatus::Pending,
        ]);

        $mail = (new ReservationTemplateNotification($reservation, EmailTemplateKey::ReservationPending))
            ->toMail($reservation->client);

        $this->assertSame([['petra@friendlyfyzio.cz', 'Petra Nováková']], $mail->replyTo);
    }
}
