<?php

namespace Tests\Feature\Enrollments;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\CourseSeriesStatus;
use App\Enums\CourseSeriesVisibility;
use App\Enums\EmailTemplateKey;
use App\Enums\PaymentStatus;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\User;
use App\Notifications\EnrollmentTemplateNotification;
use App\Support\Enrollments\JoinWaitlist;
use App\Support\Enrollments\PromoteFromWaitlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WaitlistFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    protected function fullSeries(): CourseSeries
    {
        $series = CourseSeries::factory()->create([
            'start_date' => today()->addWeeks(2)->toDateString(),
            'end_date' => today()->addWeeks(14)->toDateString(),
            'capacity' => 1,
            'price' => 2000,
            'status' => CourseSeriesStatus::Open,
        ]);

        CourseEnrollment::factory()->for($series, 'series')->create([
            'status' => CourseEnrollmentStatus::Active,
            'payment_status' => PaymentStatus::Paid,
        ]);

        return $series;
    }

    public function test_joining_a_full_offer_creates_guest_entry_and_sends_receipt(): void
    {
        $series = $this->fullSeries();

        $entry = JoinWaitlist::handle($series, 'Klára Čekající', 'klara@example.cz', '+420604111222');

        $this->assertDatabaseHas('waitlist_entries', [
            'id' => $entry->id,
            'waitlistable_type' => 'course_series',
            'waitlistable_id' => $series->id,
            'email' => 'klara@example.cz',
            'client_id' => null,
            'notified_at' => null,
        ]);

        Notification::assertSentOnDemand(EnrollmentTemplateNotification::class, fn (EnrollmentTemplateNotification $notification, array $channels, object $notifiable): bool => $notification->key === EmailTemplateKey::WaitlistJoined
            && $notification->tokens['poradi'] === '1'
            && ($notifiable->routes['mail'] ?? null) === 'klara@example.cz');
    }

    public function test_duplicate_pending_join_is_idempotent(): void
    {
        $series = $this->fullSeries();

        $first = JoinWaitlist::handle($series, 'Klára', 'klara@example.cz');
        $second = JoinWaitlist::handle($series, 'Klára', 'klara@example.cz');

        $this->assertTrue($first->is($second));
        $this->assertSame(1, $series->waitlistEntries()->count());
    }

    public function test_existing_account_is_linked_by_email(): void
    {
        $series = $this->fullSeries();
        $client = User::factory()->customer()->create(['email' => 'znama@example.cz']);

        $entry = JoinWaitlist::handle($series, 'Známá Klientka', 'znama@example.cz');

        $this->assertTrue($entry->client->is($client));
    }

    public function test_account_linking_by_email_is_case_insensitive(): void
    {
        $series = $this->fullSeries();
        $client = User::factory()->customer()->create(['email' => 'znama@example.cz']);

        $entry = JoinWaitlist::handle($series, 'Známá Klientka', 'Znama@Example.CZ');

        $this->assertTrue($entry->client->is($client));
    }

    public function test_cancellation_promotes_first_in_queue_with_unpaid_signup_and_payment(): void
    {
        $series = $this->fullSeries();

        JoinWaitlist::handle($series, 'První V Řadě', 'prvni@example.cz', '+420604999888');
        JoinWaitlist::handle($series, 'Druhá V Řadě', 'druha@example.cz');

        // The paid participant cancels — the observer must promote exactly one spot.
        $series->enrollments()->sole()->update(['status' => CourseEnrollmentStatus::Cancelled]);

        $promoted = User::query()->where('email', 'prvni@example.cz')->sole();

        $enrollment = $series->enrollments()
            ->where('client_id', $promoted->id)
            ->where('status', CourseEnrollmentStatus::Active)
            ->sole();

        $this->assertSame(PaymentStatus::Unpaid, $enrollment->payment_status);
        $this->assertSame(2000, (int) $enrollment->payments()->sole()->amount);

        $this->assertNotNull($series->waitlistEntries()->where('email', 'prvni@example.cz')->sole()->notified_at);
        $this->assertNull($series->waitlistEntries()->where('email', 'druha@example.cz')->sole()->notified_at);

        Notification::assertSentTo($promoted, EnrollmentTemplateNotification::class, fn (EnrollmentTemplateNotification $notification): bool => $notification->key === EmailTemplateKey::WaitlistSpotAvailable);
    }

    public function test_cancellation_does_not_promote_when_auto_promote_is_off(): void
    {
        $series = $this->fullSeries();
        $series->update(['auto_promote_waitlist' => false]);

        JoinWaitlist::handle($series, 'První V Řadě', 'prvni@example.cz');

        // A freed spot must NOT auto-fill when the offer opted out of automation.
        $series->enrollments()->sole()->update(['status' => CourseEnrollmentStatus::Cancelled]);

        $this->assertNull($series->waitlistEntries()->where('email', 'prvni@example.cz')->sole()->notified_at);
        $this->assertFalse(User::query()->where('email', 'prvni@example.cz')->exists());

        // The manual "promote from waitlist" action ignores the flag and fills the spot.
        PromoteFromWaitlist::handle($series);

        $promoted = User::query()->where('email', 'prvni@example.cz')->sole();

        $this->assertNotNull($series->waitlistEntries()->where('email', 'prvni@example.cz')->sole()->notified_at);
        $this->assertTrue($series->enrollments()
            ->where('client_id', $promoted->id)
            ->where('status', CourseEnrollmentStatus::Active)
            ->exists());

        Notification::assertSentTo($promoted, EnrollmentTemplateNotification::class, fn (EnrollmentTemplateNotification $notification): bool => $notification->key === EmailTemplateKey::WaitlistSpotAvailable);
    }

    public function test_interest_list_is_notified_when_series_opens(): void
    {
        $course = Course::factory()->create(['published_at' => now()]);

        JoinWaitlist::handle($course, null, 'zvedava@example.cz');

        // Interest subscriptions are silent on join.
        Notification::assertNothingSentTo(new AnonymousNotifiable);

        $series = CourseSeries::factory()->for($course)->create([
            'status' => CourseSeriesStatus::Inactive,
            'start_date' => today()->addMonth()->toDateString(),
            'end_date' => today()->addMonths(4)->toDateString(),
        ]);

        $series->update(['status' => CourseSeriesStatus::Open]);

        Notification::assertSentOnDemand(EnrollmentTemplateNotification::class, fn (EnrollmentTemplateNotification $notification, array $channels, object $notifiable): bool => $notification->key === EmailTemplateKey::CourseRegistrationOpened
            && ($notifiable->routes['mail'] ?? null) === 'zvedava@example.cz'
            && $notification->tokens['kurz'] === $course->name);

        $this->assertNotNull($course->waitlistEntries()->sole()->notified_at);
    }

    public function test_interest_list_skips_private_series_until_it_goes_public(): void
    {
        $course = Course::factory()->create(['published_at' => now()]);

        JoinWaitlist::handle($course, null, 'zvedava@example.cz');

        $series = CourseSeries::factory()->for($course)->create([
            'status' => CourseSeriesStatus::Inactive,
            'visibility' => CourseSeriesVisibility::Private,
            'start_date' => today()->addMonth()->toDateString(),
            'end_date' => today()->addMonths(4)->toDateString(),
        ]);

        // Opening an invite-only run announces nothing.
        $series->update(['status' => CourseSeriesStatus::Open]);

        Notification::assertNothingSentTo(new AnonymousNotifiable);
        $this->assertNull($course->waitlistEntries()->sole()->notified_at);

        // Making the open run public IS the public registration opening.
        $series->update(['visibility' => CourseSeriesVisibility::Public]);

        Notification::assertSentOnDemand(EnrollmentTemplateNotification::class, fn (EnrollmentTemplateNotification $notification, array $channels, object $notifiable): bool => $notification->key === EmailTemplateKey::CourseRegistrationOpened
            && ($notifiable->routes['mail'] ?? null) === 'zvedava@example.cz');

        $this->assertNotNull($course->waitlistEntries()->sole()->notified_at);
    }
}
