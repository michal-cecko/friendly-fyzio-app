<?php

namespace Tests\Feature;

use App\Enums\CourseEnrollmentStatus;
use App\Filament\Clusters\Kurzy\Resources\CourseLessons\Pages\ViewCourseLesson;
use App\Filament\Clusters\Kurzy\Resources\CourseLessons\RelationManagers\AttendancesRelationManager;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\CourseSeries;
use App\Models\LessonAttendance;
use App\Models\SubstituteToken;
use App\Models\User;
use App\Notifications\SubstituteTokenNotification;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Staff answer "Zúčastní se?" on an upcoming lesson to free a spot for somebody
 * else, and can take that answer back. Neither direction happens silently.
 */
class LessonAttendanceToggleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());
    }

    /**
     * A lesson starting at the given offset from now — date and time are derived
     * from the same instant, so "+2 hours" really is two hours away whatever the
     * clock says when the suite runs.
     */
    private function lesson(string $startingIn = '+1 week'): CourseLesson
    {
        $course = Course::factory()->create([
            'early_cancel_hours' => 24,
            'max_substitutions' => 2,
        ]);

        $series = CourseSeries::factory()->create([
            'course_id' => $course->getKey(),
            'capacity' => 10,
        ]);

        $start = now()->modify($startingIn);

        return CourseLesson::factory()->create([
            'series_id' => $series->getKey(),
            'lesson_date' => $start->format('Y-m-d'),
            'start_time' => $start->format('H:i'),
            'end_time' => $start->copy()->addHour()->format('H:i'),
        ]);
    }

    private function enroll(CourseLesson $lesson): CourseEnrollment
    {
        return CourseEnrollment::factory()->create([
            'series_id' => $lesson->series_id,
            'status' => CourseEnrollmentStatus::Active,
        ]);
    }

    private function attendance(CourseLesson $lesson, CourseEnrollment $enrollment): LessonAttendance
    {
        return LessonAttendance::query()
            ->where('lesson_id', $lesson->getKey())
            ->where('enrollment_id', $enrollment->getKey())
            ->firstOrFail();
    }

    private function relationManager(CourseLesson $lesson): Testable
    {
        return Livewire::test(AttendancesRelationManager::class, [
            'ownerRecord' => $lesson,
            'pageClass' => ViewCourseLesson::class,
        ]);
    }

    public function test_an_upcoming_lesson_asks_whether_the_client_will_attend(): void
    {
        $lesson = $this->lesson();
        $this->enroll($lesson);

        $this->relationManager($lesson)
            ->assertTableColumnExists('will_attend')
            ->assertSee('Zúčastní se?');
    }

    public function test_a_past_lesson_records_who_turned_up(): void
    {
        $lesson = $this->lesson('-1 week');
        $this->enroll($lesson);

        $this->relationManager($lesson)
            ->assertTableColumnExists('attended')
            ->assertSee('Účast');
    }

    public function test_marking_a_client_as_not_coming_frees_a_spot(): void
    {
        Notification::fake();

        $lesson = $this->lesson();
        $enrollment = $this->enroll($lesson);

        $this->assertSame(1, $lesson->fresh()->takenSpots());

        $this->relationManager($lesson)
            ->callAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)), [
                'generate_substitute' => false,
                'notify_client' => false,
            ])
            ->assertHasNoActionErrors();

        $this->assertNotNull($this->attendance($lesson, $enrollment)->cancelled_at);
        $this->assertSame(0, $lesson->fresh()->takenSpots());
        $this->assertSame(10, $lesson->fresh()->spotsLeft());

        Notification::assertNothingSent();
    }

    public function test_it_can_mint_a_substitute_and_tell_the_client(): void
    {
        Notification::fake();

        $lesson = $this->lesson();
        $enrollment = $this->enroll($lesson);

        $this->relationManager($lesson)
            ->callAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)), [
                'generate_substitute' => true,
                'notify_client' => true,
            ]);

        $this->assertSame(1, SubstituteToken::query()->where('source_lesson_id', $lesson->getKey())->count());
        $this->assertTrue($this->attendance($lesson, $enrollment)->token_generated);

        Notification::assertSentTo($enrollment->client, SubstituteTokenNotification::class);
    }

    public function test_a_late_excuse_mints_no_substitute(): void
    {
        Notification::fake();

        $lesson = $this->lesson('+2 hours');
        $enrollment = $this->enroll($lesson);

        $this->relationManager($lesson)
            ->callAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)), [
                'generate_substitute' => true,
                'notify_client' => true,
            ]);

        $this->assertSame(0, SubstituteToken::query()->count());
        $this->assertNotNull($this->attendance($lesson, $enrollment)->cancelled_at);

        Notification::assertNothingSent();
    }

    public function test_both_directions_are_written_to_the_activity_log(): void
    {
        Notification::fake();

        $lesson = $this->lesson();
        $enrollment = $this->enroll($lesson);

        $this->relationManager($lesson)
            ->callAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)), [
                'generate_substitute' => false,
                'notify_client' => false,
            ]);

        $this->relationManager($lesson)
            ->callAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)));

        $this->assertSame(1, Activity::query()->where('event', 'lesson_absence')->count());
        $this->assertSame(1, Activity::query()->where('event', 'lesson_absence_reverted')->count());
    }

    public function test_putting_a_client_back_reclaims_the_spot_and_withdraws_the_substitute(): void
    {
        Notification::fake();

        $lesson = $this->lesson();
        $enrollment = $this->enroll($lesson);

        $this->relationManager($lesson)
            ->callAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)), [
                'generate_substitute' => true,
                'notify_client' => false,
            ]);

        $this->assertSame(1, SubstituteToken::query()->count());

        $this->relationManager($lesson)
            ->callAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)));

        $attendance = $this->attendance($lesson, $enrollment);

        $this->assertNull($attendance->cancelled_at);
        $this->assertFalse($attendance->token_generated);
        $this->assertSame(0, SubstituteToken::query()->count());
        $this->assertSame(1, $lesson->fresh()->takenSpots());
    }

    public function test_a_redeemed_substitute_cannot_be_taken_back(): void
    {
        Notification::fake();

        $lesson = $this->lesson();
        $enrollment = $this->enroll($lesson);

        $this->relationManager($lesson)
            ->callAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)), [
                'generate_substitute' => true,
                'notify_client' => false,
            ]);

        SubstituteToken::query()->firstOrFail()->update([
            'used_at' => now(),
            'used_for_lesson_id' => $this->lesson()->getKey(),
        ]);

        $this->relationManager($lesson)
            ->callAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)));

        $this->assertNotNull($this->attendance($lesson, $enrollment)->cancelled_at);
        $this->assertSame(1, SubstituteToken::query()->count());
    }

    public function test_a_client_cannot_be_returned_to_a_lesson_that_filled_up(): void
    {
        Notification::fake();

        $lesson = $this->lesson();
        $lesson->series->update(['capacity' => 1]);
        $enrollment = $this->enroll($lesson);

        $this->relationManager($lesson)
            ->callAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)), [
                'generate_substitute' => false,
                'notify_client' => false,
            ]);

        // Somebody else took the freed spot in the meantime.
        $foreign = CourseEnrollment::factory()->create([
            'series_id' => CourseSeries::factory()->create()->getKey(),
            'status' => CourseEnrollmentStatus::Active,
        ]);

        LessonAttendance::create([
            'enrollment_id' => $foreign->getKey(),
            'lesson_id' => $lesson->getKey(),
            'attended' => false,
        ]);

        $this->relationManager($lesson)
            ->callAction(TestAction::make('toggleAttendance')->table($this->attendance($lesson, $enrollment)));

        $this->assertNotNull($this->attendance($lesson, $enrollment)->cancelled_at);
    }
}
