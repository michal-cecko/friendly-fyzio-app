<?php

namespace Tests\Feature;

use App\Enums\EmailTemplateKey;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\User;
use App\Notifications\ReservationTemplateNotification;
use App\Support\Reservations\ReservationDocuments;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * The passwordless half of the doctor-note flow: the link that reaches the client
 * by e-mail. It needs its own signature because the manage link expires when the
 * visit starts — always before a note from the doctor can arrive.
 */
class DoctorNoteUploadLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(ReservationDocuments::DISK);
    }

    private function reservation(array $attributes = []): Reservation
    {
        return Reservation::factory()->create([
            'client_id' => User::factory()->customer()->create()->getKey(),
            'service_id' => Service::factory()->create(['price' => 1000])->getKey(),
            'reservation_date' => today()->subDay()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'status' => ReservationStatus::Cancelled,
            'payment_status' => PaymentStatus::Unpaid,
            'doctor_note_requested_at' => now()->subDay(),
            ...$attributes,
        ]);
    }

    public function test_the_signed_page_renders_and_accepts_an_upload(): void
    {
        $reservation = $this->reservation();
        $url = $reservation->doctorNoteUploadUrl();

        $this->get($url)->assertOk()->assertSee('Doručení potvrzení od lékaře');

        $this->post($url, [
            'action' => 'upload',
            'documents' => [UploadedFile::fake()->create('potvrzeni.pdf', 100, 'application/pdf')],
        ])->assertRedirect();

        $document = $reservation->doctorNoteDocuments()->sole();

        $this->assertSame('potvrzeni.pdf', $document->original_name);
        // No logged-in uploader on the passwordless route.
        $this->assertNull($document->uploaded_by);
        Storage::disk(ReservationDocuments::DISK)->assertExists($document->path);
    }

    public function test_a_file_can_be_removed_through_the_link(): void
    {
        $reservation = $this->reservation();
        $url = $reservation->doctorNoteUploadUrl();

        $this->post($url, [
            'action' => 'upload',
            'documents' => [UploadedFile::fake()->create('omyl.pdf', 100, 'application/pdf')],
        ]);

        $document = $reservation->doctorNoteDocuments()->sole();

        $this->post($url, ['action' => 'delete', 'document' => $document->getKey()])->assertRedirect();

        $this->assertSame(0, $reservation->doctorNoteDocuments()->count());
    }

    public function test_an_unsigned_or_expired_link_is_rejected(): void
    {
        $reservation = $this->reservation();

        $this->get(route('reservation.doctor-note', $reservation))->assertForbidden();

        $expired = URL::temporarySignedRoute(
            'reservation.doctor-note',
            now()->subMinute(),
            ['reservation' => $reservation->getKey()],
        );

        $this->get($expired)->assertForbidden();
    }

    public function test_the_link_outlives_the_visit_unlike_the_manage_link(): void
    {
        // Cancelled the day before a visit that has since passed.
        $reservation = $this->reservation(['doctor_note_requested_at' => now()]);

        $this->get($reservation->manageUrl())->assertForbidden();
        $this->get($reservation->doctorNoteUploadUrl())->assertOk();
    }

    public function test_uploading_is_closed_once_staff_resolved_the_note(): void
    {
        $reservation = $this->reservation(['doctor_note_resolved_at' => now(), 'settled_at' => now()]);

        $this->post($reservation->doctorNoteUploadUrl(), [
            'action' => 'upload',
            'documents' => [UploadedFile::fake()->create('pozde.pdf', 100, 'application/pdf')],
        ])->assertRedirect();

        $this->assertSame(0, $reservation->doctorNoteDocuments()->count());
        $this->get($reservation->doctorNoteUploadUrl())->assertSee('Storno je vyřešeno');
    }

    public function test_a_disallowed_file_type_is_rejected(): void
    {
        $reservation = $this->reservation();

        $this->post($reservation->doctorNoteUploadUrl(), [
            'action' => 'upload',
            'documents' => [UploadedFile::fake()->create('virus.exe', 20, 'application/x-msdownload')],
        ])->assertSessionHasErrors('documents.0');

        $this->assertSame(0, $reservation->doctorNoteDocuments()->count());
    }

    /**
     * The whole point of the link: the "Doručte prosím potvrzení od lékaře" e-mail
     * must actually carry it. Before this it contained no link at all.
     */
    public function test_the_doctor_note_email_carries_the_upload_link(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        $reservation = $this->reservation();

        $mail = (new ReservationTemplateNotification($reservation, EmailTemplateKey::ReservationDoctorNote))
            ->toMail($reservation->client);

        $html = view($mail->view, $mail->viewData)->render();

        $this->assertStringContainsString(route('reservation.doctor-note', $reservation, false), $html);
        $this->assertStringContainsString('Nahrát potvrzení od lékaře', $html);
        $this->assertStringNotContainsString('potvrzeni_odkaz', $html);
    }
}
