<?php

namespace Tests\Feature\Enrollments;

use App\Enums\BookingStatus;
use App\Enums\EmailTemplateKey;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Clusters\Kurzy\Resources\OneOffEventBookings\Pages\ListOneOffEventBookings;
use App\Models\OneOffEvent;
use App\Models\OneOffEventBooking;
use App\Models\User;
use App\Notifications\EnrollmentTemplateNotification;
use App\Support\Enrollments\CancelSignup;
use App\Support\Enrollments\JoinWaitlist;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class CancelSignupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());
    }

    private function confirmedBooking(int $capacity = 5): OneOffEventBooking
    {
        $event = OneOffEvent::factory()->create([
            'capacity' => $capacity,
            'price' => 900,
            'event_date' => today()->addWeeks(2)->toDateString(),
        ]);

        $booking = OneOffEventBooking::factory()->create([
            'one_off_event_id' => $event->getKey(),
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
            'paid_at' => null,
        ]);

        $booking->payments()->create([
            'client_id' => $booking->client_id,
            'amount' => 900,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Unpaid,
            'due_at' => now()->addHours(48),
        ]);

        return $booking;
    }

    public function test_service_cancels_withdraws_open_payment_and_emails_clinic_template(): void
    {
        Notification::fake();

        $booking = $this->confirmedBooking();

        app(CancelSignup::class)($booking, true, EmailTemplateKey::EnrollmentCancelledByClinic);

        $booking->refresh();
        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertSame(0, $booking->payments()->count());

        Notification::assertSentTo(
            $booking->client,
            EnrollmentTemplateNotification::class,
            fn (EnrollmentTemplateNotification $notification): bool => $notification->key === EmailTemplateKey::EnrollmentCancelledByClinic,
        );
    }

    public function test_service_can_skip_the_client_email(): void
    {
        Notification::fake();

        $booking = $this->confirmedBooking();

        app(CancelSignup::class)($booking, false);

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        Notification::assertNothingSentTo($booking->client);
    }

    public function test_cancelling_frees_the_spot_and_promotes_the_waitlist(): void
    {
        Notification::fake();

        // A full event with someone waiting.
        $booking = $this->confirmedBooking(capacity: 1);
        JoinWaitlist::handle($booking->event, 'Náhradník Nový', 'nahradnik@example.cz');

        app(CancelSignup::class)($booking, false);

        // Auto-promotion (default on) fills the freed spot from the waitlist.
        $promoted = User::query()->where('email', 'nahradnik@example.cz')->sole();
        $this->assertTrue($booking->event->bookings()
            ->where('client_id', $promoted->id)
            ->whereIn('status', BookingStatus::occupying())
            ->exists());
    }

    public function test_admin_row_action_cancels_the_booking(): void
    {
        Notification::fake();

        $booking = $this->confirmedBooking();

        Livewire::test(ListOneOffEventBookings::class)
            ->callAction(TestAction::make('cancelSignup')->table($booking), [
                'notify_client' => false,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
    }

    public function test_admin_row_action_hidden_for_already_cancelled(): void
    {
        $booking = OneOffEventBooking::factory()->create([
            'status' => BookingStatus::Cancelled,
        ]);

        Livewire::test(ListOneOffEventBookings::class)
            ->assertActionHidden(TestAction::make('cancelSignup')->table($booking));
    }

    public function test_admin_bulk_action_cancels_selected_bookings(): void
    {
        Notification::fake();

        $event = OneOffEvent::factory()->create(['capacity' => 10]);
        $bookings = OneOffEventBooking::factory()->count(3)->create([
            'one_off_event_id' => $event->getKey(),
            'status' => BookingStatus::Confirmed,
        ]);

        Livewire::test(ListOneOffEventBookings::class)
            ->set('selectedTableRecords', $bookings->pluck('id')->all())
            ->callAction(TestAction::make('cancelSignups')->table()->bulk(), [
                'notify_client' => false,
            ])
            ->assertHasNoActionErrors();

        $bookings->each(function (OneOffEventBooking $booking): void {
            $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        });
    }
}
