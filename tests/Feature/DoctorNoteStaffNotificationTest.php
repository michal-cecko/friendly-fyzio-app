<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\User;
use App\Support\Reservations\ClientReservationActions;
use App\Support\Reservations\ReservationDocuments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The two doctor-note storno notifications land in the admin bell. Each one is
 * about a specific reservation, so it must carry a link straight to that
 * reservation's view page — otherwise the admin has to hunt for it by hand.
 */
class DoctorNoteStaffNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(ReservationDocuments::DISK);

        $this->admin = User::factory()->admin()->create();
    }

    private function reservation(): Reservation
    {
        return Reservation::factory()->create([
            'client_id' => User::factory()->customer()->create()->getKey(),
            'service_id' => Service::factory()->create(['price' => 1000])->getKey(),
            'reservation_date' => today()->subDay()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'status' => ReservationStatus::Cancelled,
            'payment_status' => PaymentStatus::Unpaid,
            'doctor_note_requested_at' => now()->subHours(2),
        ]);
    }

    public function test_the_uploaded_note_notification_links_to_the_reservation(): void
    {
        $reservation = $this->reservation();

        app(ReservationDocuments::class)->store(
            $reservation,
            UploadedFile::fake()->create('potvrzeni.pdf', 100, 'application/pdf'),
        );

        $notification = $this->admin->notifications()->sole();

        $this->assertSame('Potvrzení od lékaře nahráno', $notification->data['title']);
        $this->assertSame(
            ReservationResource::getUrl('view', ['record' => $reservation]),
            $notification->data['actions'][0]['url'],
        );
    }

    public function test_the_promised_note_notification_links_to_the_reservation(): void
    {
        $reservation = $this->reservation();

        app(ClientReservationActions::class)->notifyStaffOfDoctorNote($reservation);

        $notification = $this->admin->notifications()->sole();

        $this->assertSame('Storno s potvrzením od lékaře', $notification->data['title']);
        $this->assertSame(
            ReservationResource::getUrl('view', ['record' => $reservation]),
            $notification->data['actions'][0]['url'],
        );
    }
}
