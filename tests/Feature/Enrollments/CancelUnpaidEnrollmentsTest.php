<?php

namespace Tests\Feature\Enrollments;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\CourseSeriesStatus;
use App\Enums\EmailTemplateKey;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\User;
use App\Notifications\EnrollmentTemplateNotification;
use App\Support\Enrollments\JoinWaitlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CancelUnpaidEnrollmentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    protected function series(int $capacity = 5): CourseSeries
    {
        return CourseSeries::factory()->create([
            'start_date' => today()->addWeeks(2)->toDateString(),
            'end_date' => today()->addWeeks(14)->toDateString(),
            'capacity' => $capacity,
            'price' => 2000,
            'status' => CourseSeriesStatus::Open,
        ]);
    }

    protected function unpaidEnrollment(CourseSeries $series, string $dueDate): CourseEnrollment
    {
        $enrollment = CourseEnrollment::factory()->for($series, 'series')->create([
            'status' => CourseEnrollmentStatus::Active,
            'payment_status' => PaymentStatus::Unpaid,
            'paid_at' => null,
        ]);

        $enrollment->payments()->create([
            'client_id' => $enrollment->client_id,
            'amount' => 2000,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Unpaid,
            'due_at' => $dueDate,
        ]);

        return $enrollment;
    }

    public function test_expired_unpaid_signup_is_cancelled_payment_withdrawn_and_client_notified(): void
    {
        $series = $this->series();
        $expired = $this->unpaidEnrollment($series, today()->subDay()->toDateString());
        $stillHeld = $this->unpaidEnrollment($series, today()->addDay()->toDateString());

        $this->artisan('enrollments:cancel-unpaid')->assertSuccessful();

        $this->assertSame(CourseEnrollmentStatus::Cancelled, $expired->fresh()->status);
        // Withdrawn, not erased — the record survives as "Zrušeno".
        $this->assertSame(PaymentStatus::Cancelled, $expired->payments()->sole()->status);

        $this->assertSame(CourseEnrollmentStatus::Active, $stillHeld->fresh()->status);
        $this->assertSame(1, $stillHeld->payments()->whereIn('status', PaymentStatus::openValues())->count());

        Notification::assertSentTo($expired->client, EnrollmentTemplateNotification::class, fn (EnrollmentTemplateNotification $notification): bool => $notification->key === EmailTemplateKey::EnrollmentAutoCancelled);
    }

    public function test_paid_signup_and_signup_without_payment_request_are_untouched(): void
    {
        $series = $this->series();

        $paid = CourseEnrollment::factory()->for($series, 'series')->create([
            'status' => CourseEnrollmentStatus::Active,
            'payment_status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        $adminCreated = CourseEnrollment::factory()->for($series, 'series')->create([
            'status' => CourseEnrollmentStatus::Active,
            'payment_status' => PaymentStatus::Unpaid,
            'paid_at' => null,
        ]);

        $this->artisan('enrollments:cancel-unpaid')->assertSuccessful();

        $this->assertSame(CourseEnrollmentStatus::Active, $paid->fresh()->status);
        $this->assertSame(CourseEnrollmentStatus::Active, $adminCreated->fresh()->status);
    }

    public function test_freed_spot_goes_to_the_waitlist(): void
    {
        $series = $this->series(capacity: 1);
        $expired = $this->unpaidEnrollment($series, today()->subDay()->toDateString());

        JoinWaitlist::handle($series, 'Náhradnice První', 'nahradnice@example.cz');

        $this->artisan('enrollments:cancel-unpaid')->assertSuccessful();

        $promoted = User::query()->where('email', 'nahradnice@example.cz')->sole();

        $this->assertTrue($series->enrollments()
            ->where('client_id', $promoted->id)
            ->where('status', CourseEnrollmentStatus::Active)
            ->exists());

        Notification::assertSentTo($promoted, EnrollmentTemplateNotification::class, fn (EnrollmentTemplateNotification $notification): bool => $notification->key === EmailTemplateKey::WaitlistSpotAvailable);
    }
}
