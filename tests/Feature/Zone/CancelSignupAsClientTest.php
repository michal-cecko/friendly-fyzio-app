<?php

namespace Tests\Feature\Zone;

use App\Enums\BookingStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Enums\CourseSeriesStatus;
use App\Enums\EmailTemplateKey;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\OneTimeLesson;
use App\Models\OneTimeLessonBooking;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use App\Notifications\EnrollmentTemplateNotification;
use App\Support\Enrollments\CancellationWindowClosedException;
use App\Support\Enrollments\CancelSignupAsClient;
use App\Support\Enrollments\JoinWaitlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CancelSignupAsClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    protected function enrollment(int $startsInDays): CourseEnrollment
    {
        $series = CourseSeries::factory()->create([
            'start_date' => today()->addDays($startsInDays)->toDateString(),
            'end_date' => today()->addDays($startsInDays + 90)->toDateString(),
            'capacity' => 10,
            'status' => CourseSeriesStatus::Open,
        ]);

        return CourseEnrollment::factory()->for($series, 'series')->create([
            'status' => CourseEnrollmentStatus::Active,
            'payment_status' => PaymentStatus::Unpaid,
        ]);
    }

    public function test_cancelling_inside_the_window_withdraws_the_payment_and_emails_the_client(): void
    {
        // Default window: 7 days before the series starts.
        $enrollment = $this->enrollment(startsInDays: 30);

        $enrollment->payments()->create([
            'client_id' => $enrollment->client_id,
            'amount' => 2400,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Unpaid,
            'due_at' => today()->addDays(2),
        ]);

        app(CancelSignupAsClient::class)($enrollment);

        $this->assertSame(CourseEnrollmentStatus::Cancelled, $enrollment->fresh()->status);
        $this->assertSame(0, $enrollment->payments()->count());

        Notification::assertSentTo($enrollment->client, EnrollmentTemplateNotification::class, fn (EnrollmentTemplateNotification $notification): bool => $notification->key === EmailTemplateKey::EnrollmentCancelledByClient);
    }

    public function test_cancelling_past_the_window_is_rejected(): void
    {
        $enrollment = $this->enrollment(startsInDays: 3);

        $this->assertFalse(app(CancelSignupAsClient::class)->isCancellable($enrollment));

        $this->expectException(CancellationWindowClosedException::class);

        app(CancelSignupAsClient::class)($enrollment);
    }

    public function test_a_freed_course_place_goes_to_the_waitlist(): void
    {
        $enrollment = $this->enrollment(startsInDays: 30);
        $series = $enrollment->series;

        JoinWaitlist::handle($series, 'Náhradnice První', 'nahradnice@example.cz');

        app(CancelSignupAsClient::class)($enrollment);

        $promoted = User::query()->where('email', 'nahradnice@example.cz')->sole();

        $this->assertTrue($series->enrollments()
            ->where('client_id', $promoted->id)
            ->where('status', CourseEnrollmentStatus::Active)
            ->exists());
    }

    public function test_lesson_bookings_use_an_hour_based_window(): void
    {
        // Default: 24 hours before the lesson.
        $soon = OneTimeLessonBooking::factory()
            ->for(OneTimeLesson::factory()->create([
                'lesson_date' => today()->toDateString(),
                'start_time' => now()->addHours(2)->format('H:i:s'),
            ]), 'lesson')
            ->create(['status' => BookingStatus::Confirmed]);

        $later = OneTimeLessonBooking::factory()
            ->for(OneTimeLesson::factory()->create([
                'lesson_date' => today()->addDays(5)->toDateString(),
                'start_time' => '18:00:00',
            ]), 'lesson')
            ->create(['status' => BookingStatus::Confirmed]);

        $cancel = app(CancelSignupAsClient::class);

        $this->assertFalse($cancel->isCancellable($soon));
        $this->assertTrue($cancel->isCancellable($later));

        $cancel($later);

        $this->assertSame(BookingStatus::Cancelled, $later->fresh()->status);
    }

    public function test_workshop_registrations_use_a_day_based_window(): void
    {
        $registration = WorkshopRegistration::factory()
            ->for(Workshop::factory()->create([
                'workshop_date' => today()->addDays(30)->toDateString(),
                'start_time' => '10:00:00',
            ]))
            ->create(['status' => BookingStatus::Confirmed]);

        app(CancelSignupAsClient::class)($registration);

        $this->assertSame(BookingStatus::Cancelled, $registration->fresh()->status);
    }

    public function test_an_already_cancelled_signup_cannot_be_cancelled_again(): void
    {
        $enrollment = $this->enrollment(startsInDays: 30);
        $enrollment->update(['status' => CourseEnrollmentStatus::Cancelled]);

        $this->assertFalse(app(CancelSignupAsClient::class)->isCancellable($enrollment->fresh()));
    }
}
