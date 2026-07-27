<?php

namespace Tests\Feature\Enrollments;

use App\Enums\BookingStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Enums\CourseSeriesStatus;
use App\Enums\CourseSeriesVisibility;
use App\Enums\EmailTemplateKey;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\CourseSeries;
use App\Models\Lesson;
use App\Models\User;
use App\Notifications\ClientAccountCreatedNotification;
use App\Notifications\EnrollmentTemplateNotification;
use App\Support\Enrollments\AlreadySignedUpException;
use App\Support\Enrollments\EnrollmentData;
use App\Support\Enrollments\OfferClosedException;
use App\Support\Enrollments\SignUpForOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SignUpForOfferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    protected function openSeries(array $attributes = []): CourseSeries
    {
        return CourseSeries::factory()->create([
            'start_date' => today()->addWeeks(2)->toDateString(),
            'end_date' => today()->addWeeks(14)->toDateString(),
            'capacity' => 10,
            'price' => 2400,
            'status' => CourseSeriesStatus::Open,
            ...$attributes,
        ]);
    }

    protected function guestData(array $overrides = []): EnrollmentData
    {
        return new EnrollmentData(
            name: $overrides['name'] ?? 'Jana Testovací',
            email: $overrides['email'] ?? 'jana.testovaci@example.cz',
            phone: $overrides['phone'] ?? '+420604123456',
            note: $overrides['note'] ?? 'Poznámka ke kurzu',
            client: $overrides['client'] ?? null,
        );
    }

    public function test_guest_enrollment_creates_account_enrollment_and_payment_request(): void
    {
        $series = $this->openSeries();

        $enrollment = app(SignUpForOffer::class)->forSeries($series, $this->guestData());

        $client = User::query()->where('email', 'jana.testovaci@example.cz')->sole();
        $this->assertTrue($client->isCustomer());

        $this->assertDatabaseHas('course_enrollments', [
            'id' => $enrollment->id,
            'client_id' => $client->id,
            'series_id' => $series->id,
            'status' => CourseEnrollmentStatus::Active->value,
            'payment_status' => PaymentStatus::Unpaid->value,
            'note' => 'Poznámka ke kurzu',
        ]);

        $payment = $enrollment->payments()->sole();
        $this->assertSame(2400, (int) $payment->amount);
        $this->assertSame(PaymentMethod::Qr, $payment->method);
        $this->assertSame(PaymentStatus::Unpaid, $payment->status);
        $this->assertNotNull($payment->due_at);
        $this->assertNotEmpty($payment->variable_symbol);

        Notification::assertSentTo($client, EnrollmentTemplateNotification::class, fn (EnrollmentTemplateNotification $notification): bool => $notification->key === EmailTemplateKey::CourseEnrollmentReceived
            && $notification->tokens['kurz'] === $series->course->name
            && filled($notification->tokens['qr']));

        Notification::assertSentTo($client, ClientAccountCreatedNotification::class);

        Notification::assertSentTo($series->course->instructor, EnrollmentTemplateNotification::class, fn (EnrollmentTemplateNotification $notification): bool => $notification->key === EmailTemplateKey::TherapistEnrollmentCreated);
    }

    public function test_existing_account_is_reused_and_gets_no_account_email(): void
    {
        $series = $this->openSeries();
        $client = User::factory()->customer()->create(['email' => 'stala.klientka@example.cz']);

        app(SignUpForOffer::class)->forSeries($series, $this->guestData(['email' => 'stala.klientka@example.cz']));

        $this->assertSame(1, User::query()->where('email', 'stala.klientka@example.cz')->count());
        Notification::assertNotSentTo($client, ClientAccountCreatedNotification::class);
    }

    public function test_duplicate_active_enrollment_is_rejected(): void
    {
        $series = $this->openSeries();

        app(SignUpForOffer::class)->forSeries($series, $this->guestData());

        $this->expectException(AlreadySignedUpException::class);

        app(SignUpForOffer::class)->forSeries($series, $this->guestData());
    }

    public function test_full_series_rejects_enrollment(): void
    {
        $series = $this->openSeries(['capacity' => 1]);

        app(SignUpForOffer::class)->forSeries($series, $this->guestData());

        $this->expectException(OfferClosedException::class);

        app(SignUpForOffer::class)->forSeries($series, $this->guestData(['email' => 'druha@example.cz']));
    }

    public function test_mid_series_enrollment_is_pro_rated_by_remaining_lessons(): void
    {
        $series = $this->openSeries([
            'start_date' => today()->subWeeks(4)->toDateString(),
            'end_date' => today()->addWeeks(8)->toDateString(),
            'price' => 2400,
        ]);

        foreach (range(0, 11) as $week) {
            Lesson::factory()->for($series, 'series')->create([
                'lesson_date' => today()->subWeeks(4)->addWeeks($week)->toDateString(),
            ]);
        }

        $enrollment = app(SignUpForOffer::class)->forSeries($series, $this->guestData());

        // 8 of 12 lessons still ahead → 2400 × 8/12 = 1600.
        $this->assertSame(1600, (int) $enrollment->payments()->sole()->amount);
    }

    public function test_presale_token_opens_inactive_series_but_regular_signup_stays_closed(): void
    {
        $series = $this->openSeries(['status' => CourseSeriesStatus::Inactive]);

        try {
            app(SignUpForOffer::class)->forSeries($series, $this->guestData());
            $this->fail('Inactive series must reject a regular sign-up.');
        } catch (OfferClosedException) {
            // expected
        }

        $enrollment = app(SignUpForOffer::class)->forSeries($series, $this->guestData(), viaPresale: true);

        $this->assertSame(CourseEnrollmentStatus::Active, $enrollment->status);
    }

    public function test_private_series_rejects_direct_signup_but_accepts_invite_link(): void
    {
        $series = $this->openSeries(['visibility' => CourseSeriesVisibility::Private]);

        try {
            app(SignUpForOffer::class)->forSeries($series, $this->guestData());
            $this->fail('Private series must reject a regular sign-up.');
        } catch (OfferClosedException) {
            // expected
        }

        $enrollment = app(SignUpForOffer::class)->forSeries($series, $this->guestData(), viaPresale: true);

        $this->assertSame(CourseEnrollmentStatus::Active, $enrollment->status);
    }

    public function test_event_booking_path_creates_booking_payment_and_emails(): void
    {
        $event = Lesson::factory()->standalone()->published()->create([
            'lesson_date' => today()->addWeeks(3)->toDateString(),
            'capacity' => 8,
            'price' => 3500,
        ]);

        $booking = app(SignUpForOffer::class)->forEvent($event, $this->guestData(['email' => 'akce@example.cz']));

        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(3500, (int) $booking->payments()->sole()->amount);

        Notification::assertSentTo(
            User::query()->where('email', 'akce@example.cz')->sole(),
            EnrollmentTemplateNotification::class,
            fn (EnrollmentTemplateNotification $notification): bool => $notification->key === EmailTemplateKey::EventBookingReceived
                && $notification->tokens['nazev'] === $event->name,
        );

        Notification::assertSentTo($event->instructor, EnrollmentTemplateNotification::class, fn (EnrollmentTemplateNotification $notification): bool => $notification->key === EmailTemplateKey::TherapistEnrollmentCreated);
    }

    public function test_full_event_rejects_booking(): void
    {
        $event = Lesson::factory()->standalone()->published()->create([
            'lesson_date' => today()->addWeeks(2)->toDateString(),
            'capacity' => 1,
            'price' => 450,
        ]);

        app(SignUpForOffer::class)->forEvent($event, $this->guestData());

        $this->expectException(OfferClosedException::class);

        app(SignUpForOffer::class)->forEvent($event, $this->guestData(['email' => 'druha@example.cz']));
    }

    public function test_duplicate_active_event_booking_is_rejected(): void
    {
        $event = Lesson::factory()->standalone()->published()->create([
            'lesson_date' => today()->addWeeks(2)->toDateString(),
            'capacity' => 5,
        ]);

        app(SignUpForOffer::class)->forEvent($event, $this->guestData());

        $this->expectException(AlreadySignedUpException::class);

        app(SignUpForOffer::class)->forEvent($event, $this->guestData());
    }

    public function test_presale_token_opens_unpublished_event_but_regular_signup_stays_closed(): void
    {
        $event = Lesson::factory()->standalone()->unpublished()->create([
            'lesson_date' => today()->addWeeks(2)->toDateString(),
            'capacity' => 5,
        ]);

        try {
            app(SignUpForOffer::class)->forEvent($event, $this->guestData());
            $this->fail('An unpublished event must reject a regular sign-up.');
        } catch (OfferClosedException) {
            // expected
        }

        $booking = app(SignUpForOffer::class)->forEvent($event, $this->guestData(), viaPresale: true);

        $this->assertSame(BookingStatus::Confirmed, $booking->status);
    }
}
