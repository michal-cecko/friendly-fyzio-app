<?php

namespace Tests\Feature\Lessons;

use App\Enums\BookingStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Enums\EmailTemplateKey;
use App\Enums\OfferVisibility;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\EventCategory;
use App\Models\Lesson;
use App\Models\LessonBooking;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Notifications\EnrollmentTemplateNotification;
use App\Support\Lessons\ReleaseFreeSpots;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * A place freed on a course lesson goes to the čekací listina first and to the
 * public afterwards (FSS §6.4). Selling it is the same record going on sale —
 * there is no second row — so the roster and the drop-in seats share one
 * occupancy count.
 */
class ReleaseFreeSpotsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        EventCategory::query()->firstOrCreate(
            ['slug' => 'jednorazove-lekce'],
            ['name' => 'Jednorázové lekce', 'published_at' => now()],
        );
    }

    private function lesson(int $capacity = 10, ?int $dropInPrice = 260, string $startingIn = '+1 week'): Lesson
    {
        $course = Course::factory()->create(['drop_in_price' => $dropInPrice, 'slug' => 'hormonalni-joga-'.fake()->unique()->numberBetween(1, 99999)]);
        $series = CourseSeries::factory()->for($course)->create(['capacity' => $capacity]);
        $start = now()->modify($startingIn);

        return Lesson::factory()->for($series, 'series')->create([
            'lesson_date' => $start->format('Y-m-d'),
            'start_time' => $start->format('H:i'),
            'end_time' => $start->copy()->addHour()->format('H:i'),
        ]);
    }

    private function fillSeries(Lesson $lesson, int $count): void
    {
        CourseEnrollment::factory()->count($count)->create([
            'series_id' => $lesson->series_id,
            'status' => CourseEnrollmentStatus::Active,
        ]);
    }

    private function release(): array
    {
        return app(ReleaseFreeSpots::class)();
    }

    public function test_a_course_without_a_drop_in_price_never_releases_anything(): void
    {
        $lesson = $this->lesson(dropInPrice: null);

        $this->assertSame(['invited' => 0, 'released' => 0, 'withdrawn' => 0], $this->release());
        $this->assertFalse($lesson->fresh()->isReleased());
    }

    public function test_a_free_place_goes_public_when_nobody_is_waiting(): void
    {
        $lesson = $this->lesson();

        $this->assertSame(1, $this->release()['released']);

        $lesson->refresh();

        $this->assertTrue($lesson->isReleased());
        $this->assertTrue($lesson->isPublished());
        $this->assertSame(OfferVisibility::Public, $lesson->visibility);
        $this->assertNotNull($lesson->slug);
        $this->assertSame('jednorazove-lekce', $lesson->category->slug);
        // Priced from the course, not stored on the lesson.
        $this->assertNull($lesson->getRawOriginal('price'));
        $this->assertSame(260, $lesson->price);
    }

    public function test_the_waitlist_gets_first_refusal_and_keeps_its_place_in_the_queue(): void
    {
        $lesson = $this->lesson();
        $entry = WaitlistEntry::factory()->forWaitlistable($lesson->series)->create(['client_id' => null]);

        $this->assertSame(1, $this->release()['invited']);

        $lesson->refresh();

        // Reachable by the hidden link, but not on public sale yet.
        $this->assertFalse($lesson->isReleased());
        $this->assertFalse($lesson->isPublished());
        $this->assertTrue($lesson->waitlistInviteActive());
        $this->assertNotNull($lesson->presale_token);

        // A one-lesson courtesy must not cost them their spot in the course queue.
        $this->assertNull($entry->fresh()->notified_at);

        Notification::assertSentOnDemand(
            EnrollmentTemplateNotification::class,
            fn (EnrollmentTemplateNotification $notification): bool => $notification->key === EmailTemplateKey::WaitlistSpotOffered,
        );
    }

    /**
     * Entries are never consumed, so the same person keeps being offered later
     * dates — but one at a time, not a course's worth of e-mails at once.
     */
    public function test_a_waiter_is_offered_one_lesson_of_a_series_at_a_time(): void
    {
        $lesson = $this->lesson();
        $series = $lesson->series;
        $later = Lesson::factory()->for($series, 'series')->create([
            'lesson_date' => now()->addWeeks(2)->format('Y-m-d'),
            'start_time' => '18:00',
            'end_time' => '19:00',
        ]);

        WaitlistEntry::factory()->forWaitlistable($series)->create(['client_id' => null]);

        $this->assertSame(1, $this->release()['invited']);

        $this->assertTrue($lesson->fresh()->waitlistInviteActive());
        $this->assertNull($later->fresh()->waitlist_invited_until);
        Notification::assertSentOnDemandTimes(EnrollmentTemplateNotification::class, 1);

        // Once the first lesson's window closes it goes public, and the next
        // date is offered to the very same person still on the list.
        $this->travelTo(now()->addDay()->addHour());

        $result = $this->release();

        $this->assertSame(1, $result['released']);
        $this->assertSame(1, $result['invited']);
        $this->assertTrue($later->fresh()->waitlistInviteActive());
    }

    public function test_the_place_goes_public_once_the_waitlist_window_runs_out(): void
    {
        $lesson = $this->lesson();
        WaitlistEntry::factory()->forWaitlistable($lesson->series)->create(['client_id' => null]);

        $this->release();
        $this->assertFalse($lesson->fresh()->isReleased());

        $this->travelTo(now()->addDay()->addHour());

        $this->assertSame(1, $this->release()['released']);
        $this->assertTrue($lesson->fresh()->isReleased());
    }

    public function test_the_waitlist_window_shortens_as_the_lesson_approaches(): void
    {
        $lesson = $this->lesson(startingIn: '+4 hours');
        WaitlistEntry::factory()->forWaitlistable($lesson->series)->create(['client_id' => null]);

        $this->release();

        // Cutoff is 2h before the start, so ~2h remain and the waitlist gets half.
        $until = $lesson->fresh()->waitlist_invited_until;

        $this->assertNotNull($until);
        $this->assertTrue($until->lessThan(now()->addHours(2)), 'The window must not outlast the sales cutoff.');
    }

    public function test_a_lesson_inside_the_cutoff_is_never_sold(): void
    {
        $lesson = $this->lesson(startingIn: '+1 hour');
        WaitlistEntry::factory()->forWaitlistable($lesson->series)->create(['client_id' => null]);

        $this->assertSame(['invited' => 0, 'released' => 0, 'withdrawn' => 0], $this->release());
        $this->assertFalse($lesson->fresh()->isReleased());
    }

    public function test_a_full_series_offers_nothing(): void
    {
        $lesson = $this->lesson(capacity: 2);
        $this->fillSeries($lesson, 2);

        $this->assertSame(0, $this->release()['released']);
        $this->assertFalse($lesson->fresh()->isReleased());
    }

    public function test_a_released_lesson_is_withdrawn_when_the_series_fills_up_again(): void
    {
        $lesson = $this->lesson(capacity: 1);

        $this->assertSame(1, $this->release()['released']);

        $this->fillSeries($lesson, 1);

        $this->assertSame(1, $this->release()['withdrawn']);
        $this->assertFalse($lesson->fresh()->isReleased());
        $this->assertFalse($lesson->fresh()->isPublished());
    }

    public function test_a_sold_seat_keeps_the_lesson_on_sale(): void
    {
        $lesson = $this->lesson(capacity: 1);
        $this->release();

        LessonBooking::factory()->for($lesson, 'lesson')->create([
            'client_id' => User::factory()->customer()->create()->getKey(),
            'status' => BookingStatus::Confirmed,
        ]);

        // The lesson is full now, but pulling the page from under a paying
        // customer would be worse than leaving it up.
        $this->assertSame(0, $this->release()['withdrawn']);
        $this->assertTrue($lesson->fresh()->isReleased());
    }

    public function test_a_drop_in_seat_counts_against_the_room(): void
    {
        $lesson = $this->lesson(capacity: 3);
        $this->fillSeries($lesson, 2);

        $this->assertSame(1, $lesson->fresh()->spotsLeft());

        LessonBooking::factory()->for($lesson, 'lesson')->create([
            'client_id' => User::factory()->customer()->create()->getKey(),
            'status' => BookingStatus::Confirmed,
        ]);

        $lesson = $lesson->fresh();

        $this->assertSame(3, $lesson->takenSpots());
        $this->assertSame(0, $lesson->spotsLeft());
        $this->assertTrue($lesson->isFull());
    }
}
