<?php

namespace Tests\Feature\Reviews;

use App\Enums\ReviewRequestChannel;
use App\Enums\SettingValueType;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\OneOffEvent;
use App\Models\OneOffEventBooking;
use App\Models\ReviewRequest;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ReviewRequestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendReviewRequestsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function enableReviews(): void
    {
        Setting::updateOrCreate(['key' => 'reviews.enabled'], [
            'value' => '1',
            'type' => SettingValueType::Boolean,
            'label' => 'Recenze',
            'group' => 'Recenze',
        ]);
    }

    private function seriesEndingInWindow(): CourseSeries
    {
        return CourseSeries::factory()->create([
            'course_id' => Course::factory()->create()->getKey(),
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
        $this->assertNotNull(ReviewRequest::where('user_id', $clientA->getKey())->first()?->token);
        Notification::assertSentTo($clientA, ReviewRequestNotification::class);
        Notification::assertSentTo($clientB, ReviewRequestNotification::class);
    }

    public function test_sends_requests_to_event_participants(): void
    {
        Notification::fake();
        $this->enableReviews();

        $event = OneOffEvent::factory()->create(['event_date' => now()->subDays(2)->toDateString()]);
        $client = User::factory()->customer()->create();
        OneOffEventBooking::factory()->create(['one_off_event_id' => $event->getKey(), 'client_id' => $client->getKey()]);

        $this->artisan('reviews:send-requests')->assertSuccessful();

        $this->assertDatabaseHas('review_requests', [
            'user_id' => $client->getKey(),
            'reviewable_type' => 'one_off_event',
            'reviewable_id' => $event->getKey(),
            'channel' => ReviewRequestChannel::Automatic->value,
        ]);
        Notification::assertSentTo($client, ReviewRequestNotification::class);
    }

    public function test_sends_requests_for_course_linked_events_and_targets_the_course(): void
    {
        Notification::fake();
        $this->enableReviews();

        // Lesson-type (course-derived) events are covered by the automatic
        // command too; a submitted review will attach to the parent course.
        $course = Course::factory()->create();
        $event = OneOffEvent::factory()->withCourse($course)->create([
            'event_date' => now()->subDays(2)->toDateString(),
        ]);
        $client = User::factory()->customer()->create();
        OneOffEventBooking::factory()->create(['one_off_event_id' => $event->getKey(), 'client_id' => $client->getKey()]);

        $this->artisan('reviews:send-requests')->assertSuccessful();

        $request = ReviewRequest::query()
            ->where('reviewable_type', 'one_off_event')
            ->where('reviewable_id', $event->getKey())
            ->sole();

        $this->assertTrue($request->reviewTarget()->is($course));
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
            'course_id' => Course::factory()->create()->getKey(),
            'end_date' => now()->subDays(20)->toDateString(),
        ]);
        $future = CourseSeries::factory()->create([
            'course_id' => Course::factory()->create()->getKey(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        foreach ([$old, $future] as $series) {
            CourseEnrollment::factory()->create([
                'series_id' => $series->getKey(),
                'client_id' => User::factory()->customer()->create()->getKey(),
            ]);
        }

        $oldEvent = OneOffEvent::factory()->create(['event_date' => now()->subDays(20)->toDateString()]);
        OneOffEventBooking::factory()->create([
            'one_off_event_id' => $oldEvent->getKey(),
            'client_id' => User::factory()->customer()->create()->getKey(),
        ]);

        $this->artisan('reviews:send-requests')->assertSuccessful();

        $this->assertDatabaseCount('review_requests', 0);
        Notification::assertNothingSent();
    }
}
