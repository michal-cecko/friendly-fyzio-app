<?php

namespace Tests\Feature\Zone;

use App\Enums\PaymentStatus;
use App\Enums\ReservationDocumentType;
use App\Enums\ReservationStatus;
use App\Livewire\Zone\ReservationDetail;
use App\Models\Reservation;
use App\Models\ReservationDocument;
use App\Models\Service;
use App\Models\User;
use App\Support\Reservations\ClientReservationState;
use App\Support\Reservations\ReservationDocuments;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Delivering the doctor's note from the client zone: the file lands on the private
 * disk, the badge flips to „nahráno", and staff get told. Uploading is only open
 * while the promised note is unresolved.
 */
class ZoneDoctorNoteUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Storage::fake(ReservationDocuments::DISK);

        $this->client = User::factory()->customer()->create(['email_verified_at' => now()]);
        $this->service = Service::factory()->create(['price' => 1000]);
    }

    private function reservation(array $attributes = []): Reservation
    {
        return Reservation::factory()->create([
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'reservation_date' => today()->subDay()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'status' => ReservationStatus::Cancelled,
            'payment_status' => PaymentStatus::Unpaid,
            'cancellation_reason' => 'Pozdní storno – potvrzení od lékaře',
            'doctor_note_requested_at' => now()->subHours(2),
            ...$attributes,
        ]);
    }

    public function test_the_state_flips_from_awaiting_to_submitted(): void
    {
        $reservation = $this->reservation();

        $this->assertSame(
            ClientReservationState::AwaitingDoctorNote,
            ClientReservationState::for($reservation->load('payments', 'doctorNoteDocuments')),
        );

        Livewire::actingAs($this->client)
            ->test(ReservationDetail::class, ['reservation' => $reservation])
            ->assertSee('Čeká na potvrzení od lékaře')
            ->set('doctorNoteFiles', [UploadedFile::fake()->create('potvrzeni.pdf', 120, 'application/pdf')])
            ->call('uploadDoctorNote')
            ->assertHasNoErrors()
            ->assertSee('Potvrzení nahráno – čeká na schválení');

        $this->assertSame(
            ClientReservationState::DoctorNoteSubmitted,
            ClientReservationState::for($reservation->fresh()->load('payments', 'doctorNoteDocuments')),
        );
    }

    public function test_an_upload_stores_the_file_privately_and_alerts_staff(): void
    {
        $admin = User::factory()->admin()->create();
        $reservation = $this->reservation();

        Livewire::actingAs($this->client)
            ->test(ReservationDetail::class, ['reservation' => $reservation])
            ->set('doctorNoteFiles', [UploadedFile::fake()->create('potvrzeni.pdf', 120, 'application/pdf')])
            ->call('uploadDoctorNote')
            ->assertHasNoErrors();

        $document = $reservation->doctorNoteDocuments()->sole();

        $this->assertSame('potvrzeni.pdf', $document->original_name);
        $this->assertSame(ReservationDocumentType::DoctorNote, $document->type);
        $this->assertSame($this->client->getKey(), $document->uploaded_by);
        Storage::disk(ReservationDocuments::DISK)->assertExists($document->path);
        $this->assertStringStartsWith(ReservationDocuments::DIRECTORY.'/'.$reservation->getKey(), $document->path);

        Notification::assertSentTo($admin, DatabaseNotification::class);

        // The fee stays suspended — the upload is evidence, not a decision.
        $this->assertNull($reservation->fresh()->doctor_note_resolved_at);
        $this->assertNull($reservation->fresh()->settled_at);
        $this->assertSame(0, $reservation->payments()->count());
    }

    public function test_a_photo_of_the_note_is_accepted(): void
    {
        $reservation = $this->reservation();

        Livewire::actingAs($this->client)
            ->test(ReservationDetail::class, ['reservation' => $reservation])
            ->set('doctorNoteFiles', [UploadedFile::fake()->create('IMG_0421.heic', 900, 'image/heic')])
            ->call('uploadDoctorNote')
            ->assertHasNoErrors();

        $this->assertSame('IMG_0421.heic', $reservation->doctorNoteDocuments()->sole()->original_name);
    }

    public function test_a_disallowed_file_type_is_rejected(): void
    {
        $reservation = $this->reservation();

        Livewire::actingAs($this->client)
            ->test(ReservationDetail::class, ['reservation' => $reservation])
            ->set('doctorNoteFiles', [UploadedFile::fake()->create('virus.exe', 20, 'application/x-msdownload')])
            ->call('uploadDoctorNote')
            ->assertHasErrors('doctorNoteFiles.0');

        $this->assertSame(0, $reservation->doctorNoteDocuments()->count());
    }

    public function test_an_oversized_file_is_rejected(): void
    {
        $reservation = $this->reservation();

        Livewire::actingAs($this->client)
            ->test(ReservationDetail::class, ['reservation' => $reservation])
            ->set('doctorNoteFiles', [UploadedFile::fake()->create('sken.pdf', 11 * 1024, 'application/pdf')])
            ->call('uploadDoctorNote')
            ->assertHasErrors('doctorNoteFiles.0');

        $this->assertSame(0, $reservation->doctorNoteDocuments()->count());
    }

    public function test_a_note_can_be_removed_until_staff_resolve_it(): void
    {
        $reservation = $this->reservation();

        $component = Livewire::actingAs($this->client)
            ->test(ReservationDetail::class, ['reservation' => $reservation])
            ->set('doctorNoteFiles', [UploadedFile::fake()->create('omyl.pdf', 40, 'application/pdf')])
            ->call('uploadDoctorNote');

        $document = $reservation->doctorNoteDocuments()->sole();
        $path = $document->path;

        $component->call('removeDoctorNote', $document->getKey());

        $this->assertSame(0, $reservation->doctorNoteDocuments()->count());
        Storage::disk(ReservationDocuments::DISK)->assertMissing($path);
    }

    public function test_uploading_is_closed_once_staff_resolved_the_note(): void
    {
        $reservation = $this->reservation(['doctor_note_resolved_at' => now(), 'settled_at' => now()]);

        Livewire::actingAs($this->client)
            ->test(ReservationDetail::class, ['reservation' => $reservation])
            ->assertDontSee('Nahrát potvrzení')
            ->set('doctorNoteFiles', [UploadedFile::fake()->create('pozde.pdf', 40, 'application/pdf')])
            ->call('uploadDoctorNote');

        $this->assertSame(0, $reservation->doctorNoteDocuments()->count());
    }

    public function test_a_resolved_note_can_no_longer_be_deleted(): void
    {
        $reservation = $this->reservation();

        $document = app(ReservationDocuments::class)->store(
            $reservation,
            UploadedFile::fake()->create('potvrzeni.pdf', 40, 'application/pdf'),
        );

        $reservation->update(['doctor_note_resolved_at' => now(), 'settled_at' => now()]);

        $this->assertFalse(app(ReservationDocuments::class)->delete($document->fresh()));
        $this->assertModelExists($document);
    }

    public function test_the_owner_and_staff_can_download_but_a_stranger_cannot(): void
    {
        $reservation = $this->reservation();

        $document = app(ReservationDocuments::class)->store(
            $reservation,
            UploadedFile::fake()->create('potvrzeni.pdf', 40, 'application/pdf'),
        );

        $this->actingAs($this->client)->get($document->downloadUrl())->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get($document->downloadUrl())->assertOk();

        $stranger = User::factory()->customer()->create(['email_verified_at' => now()]);
        $this->actingAs($stranger)->get($document->downloadUrl())->assertNotFound();
    }

    public function test_deleting_a_reservation_takes_its_files_with_it(): void
    {
        $reservation = $this->reservation();

        $document = app(ReservationDocuments::class)->store(
            $reservation,
            UploadedFile::fake()->create('potvrzeni.pdf', 40, 'application/pdf'),
        );

        app(ReservationDocuments::class)->purge($reservation->fresh()->load('documents'));

        $this->assertDatabaseMissing((new ReservationDocument)->getTable(), ['id' => $document->getKey()]);
        Storage::disk(ReservationDocuments::DISK)->assertMissing($document->path);
    }
}
