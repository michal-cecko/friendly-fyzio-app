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
use App\Models\EventCategory;
use App\Models\Lesson;
use App\Models\LessonBooking;
use App\Models\User;
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
        // Withdrawn, not erased — the record survives as "Zrušeno".
        $this->assertSame(PaymentStatus::Cancelled, $enrollment->payments()->sole()->status);

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

    /**
     * A booking on an event starting `$startsInHours` from now, optionally with
     * its own window and/or a category carrying one.
     */
    protected function booking(int $startsInHours, ?int $lessonHours = null, ?int $categoryHours = null): LessonBooking
    {
        $starts = now()->addHours($startsInHours);

        $lesson = Lesson::factory()->create([
            'lesson_date' => $starts->toDateString(),
            'start_time' => $starts->format('H:i:s'),
            'cancel_before_hours' => $lessonHours,
            'event_category_id' => $categoryHours === null
                ? null
                : EventCategory::factory()->create(['cancel_before_hours' => $categoryHours])->id,
        ]);

        return LessonBooking::factory()
            ->for($lesson, 'lesson')
            ->create(['status' => BookingStatus::Confirmed]);
    }

    public function test_event_bookings_fall_back_to_the_clinic_wide_hour_window(): void
    {
        // Nothing set on the event or its category: the 24 h setting decides.
        $cancel = app(CancelSignupAsClient::class);

        $this->assertFalse($cancel->isCancellable($this->booking(startsInHours: 2)));

        $later = $this->booking(startsInHours: 24 * 5);
        $this->assertTrue($cancel->isCancellable($later));

        $cancel($later);

        $this->assertSame(BookingStatus::Cancelled, $later->fresh()->status);
    }

    public function test_the_category_window_overrides_the_setting(): void
    {
        // "Workshopy": a week's notice, so 30 hours ahead is already too late.
        $cancel = app(CancelSignupAsClient::class);

        $this->assertFalse($cancel->isCancellable($this->booking(startsInHours: 30, categoryHours: 168)));
        $this->assertTrue($cancel->isCancellable($this->booking(startsInHours: 24 * 10, categoryHours: 168)));
    }

    public function test_the_event_window_overrides_its_category(): void
    {
        // A demanding workshop in an otherwise 48 h category.
        $cancel = app(CancelSignupAsClient::class);

        $this->assertFalse($cancel->isCancellable(
            $this->booking(startsInHours: 24 * 5, lessonHours: 240, categoryHours: 48)
        ));
        // And the other way round: a laxer event inside a strict category.
        $this->assertTrue($cancel->isCancellable(
            $this->booking(startsInHours: 30, lessonHours: 24, categoryHours: 168)
        ));
    }

    public function test_an_already_cancelled_signup_cannot_be_cancelled_again(): void
    {
        $enrollment = $this->enrollment(startsInDays: 30);
        $enrollment->update(['status' => CourseEnrollmentStatus::Cancelled]);

        $this->assertFalse(app(CancelSignupAsClient::class)->isCancellable($enrollment->fresh()));
    }
}
