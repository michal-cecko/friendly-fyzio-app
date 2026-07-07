<?php

namespace Tests\Feature\Reviews;

use App\Enums\ReviewRequestChannel;
use App\Enums\SettingValueType;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use App\Notifications\ReviewRequestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendReviewRequestsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function enableReviews(?string $globalUrl = null): void
    {
        Setting::updateOrCreate(['key' => 'reviews.enabled'], [
            'value' => '1',
            'type' => SettingValueType::Boolean,
            'label' => 'Recenze',
            'group' => 'Recenze',
        ]);

        if ($globalUrl !== null) {
            Setting::updateOrCreate(['key' => 'reviews.questionnaire_url'], [
                'value' => $globalUrl,
                'type' => SettingValueType::Text,
                'label' => 'URL',
                'group' => 'Recenze',
            ]);
        }
    }

    private function seriesEndingInWindow(?string $questionnaireUrl = 'https://forms.test/kurz'): CourseSeries
    {
        $course = Course::factory()->create(['questionnaire_url' => $questionnaireUrl]);

        return CourseSeries::factory()->create([
            'course_id' => $course->getKey(),
            'end_date' => now()->subDays(2)->toDateString(),
        ]);
    }

    public function test_sends_requests_to_course_series_participants(): void
    {
        Notification::fake();
        $this->enableReviews();

        $series = $this->seriesEndingInWindow();
        $clientA = User::factory()->customer()->create();
        $clientB = User::factory()->customer()->create();
        CourseEnrollment::factory()->create(['series_id' => $series->getKey(), 'client_id' => $clientA->getKey()]);
        CourseEnrollment::factory()->create(['series_id' => $series->getKey(), 'client_id' => $clientB->getKey()]);

        $this->artisan('reviews:send-requests')->assertSuccessful();

        $this->assertDatabaseCount('review_requests', 2);
        Notification::assertSentTo($clientA, ReviewRequestNotification::class);
        Notification::assertSentTo($clientB, ReviewRequestNotification::class);
    }

    public function test_sends_requests_to_workshop_registrants(): void
    {
        Notification::fake();
        $this->enableReviews();

        $workshop = Workshop::factory()->create([
            'workshop_date' => now()->subDays(2)->toDateString(),
            'questionnaire_url' => 'https://forms.test/workshop',
        ]);
        $client = User::factory()->customer()->create();
        WorkshopRegistration::factory()->create(['workshop_id' => $workshop->getKey(), 'client_id' => $client->getKey()]);

        $this->artisan('reviews:send-requests')->assertSuccessful();

        $this->assertDatabaseHas('review_requests', [
            'user_id' => $client->getKey(),
            'reviewable_type' => 'workshop',
            'reviewable_id' => $workshop->getKey(),
            'channel' => ReviewRequestChannel::Automatic->value,
            'questionnaire_url' => 'https://forms.test/workshop',
        ]);
        Notification::assertSentTo($client, ReviewRequestNotification::class);
    }

    public function test_is_idempotent_across_runs(): void
    {
        Notification::fake();
        $this->enableReviews();

        $series = $this->seriesEndingInWindow();
        $client = User::factory()->customer()->create();
        CourseEnrollment::factory()->create(['series_id' => $series->getKey(), 'client_id' => $client->getKey()]);

        $this->artisan('reviews:send-requests')->assertSuccessful();
        $this->artisan('reviews:send-requests')->assertSuccessful();

        // Second run skips the already-requested participant.
        $this->assertDatabaseCount('review_requests', 1);
        Notification::assertSentTo($client, ReviewRequestNotification::class);
    }

    public function test_does_nothing_when_disabled(): void
    {
        Notification::fake();
        // reviews.enabled is not set → the feature defaults to off.

        $series = $this->seriesEndingInWindow();
        $client = User::factory()->customer()->create();
        CourseEnrollment::factory()->create(['series_id' => $series->getKey(), 'client_id' => $client->getKey()]);

        $this->artisan('reviews:send-requests')->assertSuccessful();

        $this->assertDatabaseCount('review_requests', 0);
        Notification::assertNothingSent();
    }

    public function test_ignores_events_outside_the_window(): void
    {
        Notification::fake();
        $this->enableReviews();

        $old = CourseSeries::factory()->create([
            'course_id' => Course::factory()->create(['questionnaire_url' => 'https://forms.test/kurz'])->getKey(),
            'end_date' => now()->subDays(20)->toDateString(),
        ]);
        $future = CourseSeries::factory()->create([
            'course_id' => Course::factory()->create(['questionnaire_url' => 'https://forms.test/kurz'])->getKey(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        foreach ([$old, $future] as $series) {
            CourseEnrollment::factory()->create([
                'series_id' => $series->getKey(),
                'client_id' => User::factory()->customer()->create()->getKey(),
            ]);
        }

        $this->artisan('reviews:send-requests')->assertSuccessful();

        $this->assertDatabaseCount('review_requests', 0);
        Notification::assertNothingSent();
    }

    public function test_skips_event_without_any_questionnaire_url(): void
    {
        Notification::fake();
        $this->enableReviews(); // no global URL configured

        $series = $this->seriesEndingInWindow(null); // course has no URL either
        $client = User::factory()->customer()->create();
        CourseEnrollment::factory()->create(['series_id' => $series->getKey(), 'client_id' => $client->getKey()]);

        $this->artisan('reviews:send-requests')->assertSuccessful();

        $this->assertDatabaseCount('review_requests', 0);
        Notification::assertNothingSent();
    }

    public function test_falls_back_to_global_questionnaire_url(): void
    {
        Notification::fake();
        $this->enableReviews('https://global.test/dotaznik');

        $series = $this->seriesEndingInWindow(null); // no per-course URL
        $client = User::factory()->customer()->create();
        CourseEnrollment::factory()->create(['series_id' => $series->getKey(), 'client_id' => $client->getKey()]);

        $this->artisan('reviews:send-requests')->assertSuccessful();

        $this->assertDatabaseHas('review_requests', [
            'user_id' => $client->getKey(),
            'questionnaire_url' => 'https://global.test/dotaznik',
        ]);
        Notification::assertSentTo($client, ReviewRequestNotification::class);
    }
}
