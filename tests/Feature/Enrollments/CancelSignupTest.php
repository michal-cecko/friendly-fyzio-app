<?php

namespace Tests\Feature\Enrollments;

use App\Enums\BookingStatus;
use App\Enums\EmailTemplateKey;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Clusters\Kurzy\Resources\LessonBookings\Pages\ListLessonBookings;
use App\Models\Lesson;
use App\Models\LessonBooking;
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

    private function confirmedBooking(int $capacity = 5): LessonBooking
    {
        $event = Lesson::factory()->create([
            'capacity' => $capacity,
            'price' => 900,
            'lesson_date' => today()->addWeeks(2)->toDateString(),
        ]);

        $booking = LessonBooking::factory()->create([
            'lesson_id' => $event->getKey(),
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
        // Withdrawn, not erased — the record survives as "Zrušeno".
        $this->assertSame(0, $booking->payments()->whereIn('status', PaymentStatus::openValues())->count());
        $this->assertSame(PaymentStatus::Cancelled, $booking->payments()->sole()->status);

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
        JoinWaitlist::handle($booking->lesson, 'Náhradník Nový', 'nahradnik@example.cz');

        app(CancelSignup::class)($booking, false);

        // Auto-promotion (default on) fills the freed spot from the waitlist.
        $promoted = User::query()->where('email', 'nahradnik@example.cz')->sole();
        $this->assertTrue($booking->lesson->bookings()
            ->where('client_id', $promoted->id)
            ->whereIn('status', BookingStatus::occupying())
            ->exists());
    }

    public function test_admin_row_action_cancels_the_booking(): void
    {
        Notification::fake();

        $booking = $this->confirmedBooking();

        Livewire::test(ListLessonBookings::class)
            ->callAction(TestAction::make('cancelSignup')->table($booking), [
                'notify_client' => false,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
    }

    public function test_admin_row_action_hidden_for_already_cancelled(): void
    {
        $booking = LessonBooking::factory()->create([
            'status' => BookingStatus::Cancelled,
        ]);

        Livewire::test(ListLessonBookings::class)
            ->assertActionHidden(TestAction::make('cancelSignup')->table($booking));
    }

    public function test_admin_row_action_can_also_hard_delete_the_booking(): void
    {
        Notification::fake();

        $booking = $this->confirmedBooking();

        Livewire::test(ListLessonBookings::class)
            ->callAction(TestAction::make('cancelSignup')->table($booking), [
                'notify_client' => false,
                'delete_record' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertNull($booking->fresh());
    }

    public function test_admin_row_action_reverts_a_cancelled_booking(): void
    {
        $booking = LessonBooking::factory()->create([
            'status' => BookingStatus::Cancelled,
        ]);

        Livewire::test(ListLessonBookings::class)
            ->callAction(TestAction::make('revertSignup')->table($booking))
            ->assertHasNoActionErrors();

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_revert_row_action_hidden_for_active_signup(): void
    {
        $booking = $this->confirmedBooking();

        Livewire::test(ListLessonBookings::class)
            ->assertActionHidden(TestAction::make('revertSignup')->table($booking));
    }

    public function test_plain_delete_is_merged_into_cancel_for_active_signups(): void
    {
        // Zrušit (with its hard-delete toggle) is the single action on an active
        // row; the standalone delete only appears once the sign-up is cancelled.
        $active = $this->confirmedBooking();
        $cancelled = LessonBooking::factory()->create(['status' => BookingStatus::Cancelled]);

        Livewire::test(ListLessonBookings::class)
            ->assertActionHidden(TestAction::make('delete')->table($active))
            ->assertActionVisible(TestAction::make('delete')->table($cancelled));
    }

    public function test_admin_bulk_action_cancels_selected_bookings(): void
    {
        Notification::fake();

        $event = Lesson::factory()->create(['capacity' => 10]);
        $bookings = LessonBooking::factory()->count(3)->create([
            'lesson_id' => $event->getKey(),
            'status' => BookingStatus::Confirmed,
        ]);

        Livewire::test(ListLessonBookings::class)
            ->set('selectedTableRecords', $bookings->pluck('id')->all())
            ->callAction(TestAction::make('cancelSignups')->table()->bulk(), [
                'notify_client' => false,
            ])
            ->assertHasNoActionErrors();

        $bookings->each(function (LessonBooking $booking): void {
            $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        });
    }
}
