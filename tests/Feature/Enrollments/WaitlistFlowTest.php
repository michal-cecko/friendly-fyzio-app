<?php

namespace Tests\Feature\Enrollments;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\CourseSeriesStatus;
use App\Enums\CourseSeriesVisibility;
use App\Enums\EmailTemplateKey;
use App\Enums\OfferState;
use App\Enums\PaymentStatus;
use App\Enums\WaitlistPromotionMode;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\User;
use App\Notifications\EnrollmentTemplateNotification;
use App\Support\Enrollments\JoinWaitlist;
use App\Support\Enrollments\PromoteFromWaitlist;
use App\Support\Settings;
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

    public function test_cancellation_does_not_promote_in_manual_mode(): void
    {
        $series = $this->fullSeries();
        $series->update(['waitlist_promotion_mode' => WaitlistPromotionMode::Manual]);

        JoinWaitlist::handle($series, 'První V Řadě', 'prvni@example.cz');

        // A freed spot must NOT auto-fill when the offer is left to staff.
        $series->enrollments()->sole()->update(['status' => CourseEnrollmentStatus::Cancelled]);

        $this->assertNull($series->waitlistEntries()->where('email', 'prvni@example.cz')->sole()->notified_at);
        $this->assertFalse(User::query()->where('email', 'prvni@example.cz')->exists());

        // The manual "promote from waitlist" action ignores the mode and fills the spot.
        PromoteFromWaitlist::handle($series);

        $promoted = User::query()->where('email', 'prvni@example.cz')->sole();

        $this->assertNotNull($series->waitlistEntries()->where('email', 'prvni@example.cz')->sole()->notified_at);
        $this->assertTrue($series->enrollments()
            ->where('client_id', $promoted->id)
            ->where('status', CourseEnrollmentStatus::Active)
            ->exists());

        Notification::assertSentTo($promoted, EnrollmentTemplateNotification::class, fn (EnrollmentTemplateNotification $notification): bool => $notification->key === EmailTemplateKey::WaitlistSpotAvailable);
    }

    public function test_invite_mode_races_every_waiter_without_booking_anything(): void
    {
        $series = $this->fullSeries();
        $series->update(['waitlist_promotion_mode' => WaitlistPromotionMode::AutomaticInvite]);

        JoinWaitlist::handle($series, 'První V Řadě', 'prvni@example.cz');
        JoinWaitlist::handle($series, 'Druhá V Řadě', 'druha@example.cz');

        $series->enrollments()->sole()->update(['status' => CourseEnrollmentStatus::Cancelled]);

        // Nobody is signed up or given an account — the round only sends e-mails.
        $this->assertSame(0, $series->refresh()->takenSpots());
        $this->assertFalse(User::query()->whereIn('email', ['prvni@example.cz', 'druha@example.cz'])->exists());
        $this->assertDatabaseCount('payments', 0);

        // Everyone is invited at once and consumed, so the queue drains in one go.
        $this->assertNull($series->waitlistEntries()->pending()->first());

        foreach (['prvni@example.cz', 'druha@example.cz'] as $email) {
            Notification::assertSentOnDemand(
                EnrollmentTemplateNotification::class,
                fn (EnrollmentTemplateNotification $notification, array $channels, object $notifiable): bool => $notification->key === EmailTemplateKey::WaitlistSpotOffered
                    && ($notifiable->routes['mail'] ?? null) === $email
                    && $notification->tokens['odkaz'] === $series->presaleUrl(),
            );
        }
    }

    public function test_invite_window_reserves_the_spot_for_the_waitlist_then_releases_it(): void
    {
        $series = $this->fullSeries();
        $series->update(['waitlist_promotion_mode' => WaitlistPromotionMode::AutomaticInvite]);

        JoinWaitlist::handle($series, 'První V Řadě', 'prvni@example.cz');

        $series->enrollments()->sole()->update(['status' => CourseEnrollmentStatus::Cancelled]);
        $series->refresh();

        // The spot is free, yet the public form keeps showing the series as full…
        $this->assertSame(1, $series->spotsLeft());
        $this->assertTrue($series->waitlistInviteActive());
        $this->assertSame(OfferState::Full, $series->offerState());

        // …while an invited waiter gets through on the hidden link from the e-mail.
        $this->assertSame(OfferState::Open, $series->offerStateForPresale());

        // Once the window closes the spot belongs to everyone, with no cron involved.
        $this->travel(Settings::waitlistInviteHours() + 1)->hours();

        $this->assertFalse($series->waitlistInviteActive());
        $this->assertSame(OfferState::Open, $series->offerState());
    }

    public function test_availability_filter_hides_a_series_whose_spot_is_reserved_for_the_waitlist(): void
    {
        $series = $this->fullSeries();
        $series->update(['waitlist_promotion_mode' => WaitlistPromotionMode::AutomaticInvite]);

        JoinWaitlist::handle($series, 'První V Řadě', 'prvni@example.cz');
        $series->enrollments()->sole()->update(['status' => CourseEnrollmentStatus::Cancelled]);

        // Capacity alone says "free", so the public "jen volná místa" filter would
        // list a série the detail page shows as Obsazeno — the scope must drop it.
        $this->assertTrue(CourseSeries::query()->whereKey($series->getKey())->hasSpotsLeft()->exists());
        $this->assertFalse(CourseSeries::query()->whereKey($series->getKey())
            ->hasSpotsLeft()
            ->withoutActiveWaitlistInvite()
            ->exists());

        $this->travel(Settings::waitlistInviteHours() + 1)->hours();

        $this->assertTrue(CourseSeries::query()->whereKey($series->getKey())
            ->hasSpotsLeft()
            ->withoutActiveWaitlistInvite()
            ->exists());
    }

    public function test_invite_round_is_not_restarted_while_one_is_still_running(): void
    {
        $series = CourseSeries::factory()->create([
            'start_date' => today()->addWeeks(2)->toDateString(),
            'end_date' => today()->addWeeks(14)->toDateString(),
            'capacity' => 2,
            'price' => 2000,
            'status' => CourseSeriesStatus::Open,
            'waitlist_promotion_mode' => WaitlistPromotionMode::AutomaticInvite,
        ]);

        CourseEnrollment::factory()->count(2)->for($series, 'series')->create([
            'status' => CourseEnrollmentStatus::Active,
            'payment_status' => PaymentStatus::Paid,
        ]);

        JoinWaitlist::handle($series, 'První V Řadě', 'prvni@example.cz');

        $series->enrollments()->first()->update(['status' => CourseEnrollmentStatus::Cancelled]);
        $deadline = $series->refresh()->waitlist_invited_until;

        // A second spot freeing inside the window just widens the same race.
        JoinWaitlist::handle($series, 'Pozdní Zájemce', 'pozdni@example.cz');
        $series->enrollments()->where('status', CourseEnrollmentStatus::Active)->first()
            ->update(['status' => CourseEnrollmentStatus::Cancelled]);

        $this->assertEquals($deadline, $series->refresh()->waitlist_invited_until);

        // The late joiner is left pending — they were not part of the running round.
        $this->assertNull($series->waitlistEntries()->where('email', 'pozdni@example.cz')->sole()->notified_at);
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
