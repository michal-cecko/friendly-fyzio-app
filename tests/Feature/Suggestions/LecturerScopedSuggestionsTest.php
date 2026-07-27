<?php

namespace Tests\Feature\Suggestions;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\CourseSeriesStatus;
use App\Enums\PaymentStatus;
use App\Enums\WaitlistPromotionMode;
use App\Filament\Pages\Problems;
use App\Filament\Pages\Suggestions;
use App\Filament\Widgets\ProblemsWidget;
use App\Filament\Widgets\SuggestionsWidget;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\Lesson;
use App\Models\Payment;
use App\Models\Review;
use App\Models\Room;
use App\Models\TherapistWorkBlock;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Support\Suggestions\SuggestionFinder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The lecturer twin of {@see TherapistScopedSuggestionsTest}. Someone who only
 * teaches was previously shut out of both work lists altogether; they now get
 * them narrowed to the offerings they instruct.
 */
class LecturerScopedSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        Cache::flush();
    }

    private function lecturer(): User
    {
        return User::factory()->lecturer()->create();
    }

    private function seriesWithFreeSpotAndWaiter(?User $instructor = null): CourseSeries
    {
        $course = Course::factory()->create($instructor === null ? [] : ['instructor_id' => $instructor->id]);

        $series = CourseSeries::factory()->create([
            'course_id' => $course->id,
            'capacity' => 5,
            'start_date' => today()->subWeek(),
            'end_date' => today()->addMonth(),
            'status' => CourseSeriesStatus::Open,
            'waitlist_promotion_mode' => WaitlistPromotionMode::Manual,
        ]);

        CourseEnrollment::factory()->create([
            'series_id' => $series->id,
            'status' => CourseEnrollmentStatus::Active,
        ]);

        WaitlistEntry::factory()->forWaitlistable($series)->create(['notified_at' => null]);

        return $series;
    }

    public function test_a_lecturer_reaches_both_surfaces(): void
    {
        $this->actingAs($this->lecturer());

        $this->assertTrue(SuggestionsWidget::canView());
        $this->assertTrue(ProblemsWidget::canView());
        $this->assertTrue(Suggestions::canAccess());
        $this->assertTrue(Problems::canAccess());
    }

    public function test_a_lecturer_is_only_told_about_the_courses_they_instruct(): void
    {
        $lecturer = $this->lecturer();
        $mine = $this->seriesWithFreeSpotAndWaiter($lecturer);
        $this->seriesWithFreeSpotAndWaiter();

        $this->actingAs($lecturer);

        $suggestions = SuggestionFinder::all();

        $this->assertCount(1, $suggestions);
        $this->assertSame($mine->id, $suggestions[0]['id']);
    }

    public function test_office_work_stays_with_the_office(): void
    {
        Payment::factory()->create(['status' => PaymentStatus::Unpaid, 'due_at' => today()->subDay()]);
        Review::factory()->create(['visible' => false]);

        $this->actingAs($this->lecturer());

        $types = array_column(SuggestionFinder::all(), 'type');

        $this->assertNotContains('payments_past_due', $types);
        $this->assertNotContains('reviews_hidden', $types);
    }

    /**
     * A lesson sitting on somebody's working hours is the instructor's clash as
     * much as the therapist's — the lecturer's staff profile is what ties them to
     * it, which is why granting the capability creates one.
     */
    public function test_a_lecturer_only_sees_clashes_their_own_lesson_causes(): void
    {
        $lecturer = $this->lecturer();
        $room = Room::factory()->create();
        $tomorrow = today()->addDay();

        TherapistWorkBlock::factory()->create([
            'room_id' => $room->id,
            'work_date' => $tomorrow->toDateString(),
            'start_time' => '08:00',
            'end_time' => '20:00',
        ]);
        Lesson::factory()->create([
            'instructor_id' => $lecturer->id,
            'room_id' => $room->id,
            'lesson_date' => $tomorrow->toDateString(),
            'start_time' => '17:00',
            'end_time' => '18:00',
        ]);

        $this->actingAs($lecturer);

        $this->assertSame(1, Livewire::test(ProblemsWidget::class)->viewData('total'));

        // Another lecturer's lesson blocking another room is not their business.
        $otherRoom = Room::factory()->create();
        TherapistWorkBlock::factory()->create([
            'room_id' => $otherRoom->id,
            'work_date' => $tomorrow->toDateString(),
            'start_time' => '08:00',
            'end_time' => '20:00',
        ]);
        Lesson::factory()->create([
            'room_id' => $otherRoom->id,
            'lesson_date' => $tomorrow->toDateString(),
            'start_time' => '17:00',
            'end_time' => '18:00',
        ]);

        $this->assertSame(1, Livewire::test(ProblemsWidget::class)->viewData('total'));
    }
}
