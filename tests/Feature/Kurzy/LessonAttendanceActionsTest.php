<?php

namespace Tests\Feature\Kurzy;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\LessonExcuseReason;
use App\Filament\Clusters\Kurzy\Resources\LessonAttendances\LessonAttendanceResource;
use App\Filament\Clusters\Kurzy\Resources\Lessons\Pages\ViewLesson;
use App\Filament\Clusters\Kurzy\Resources\Lessons\RelationManagers\AttendancesRelationManager;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\Lesson;
use App\Models\LessonAttendance;
use App\Models\LessonBooking;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * What staff can do to a seat from the lesson's Docházka section, and what they
 * deliberately cannot.
 */
class LessonAttendanceActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        Notification::fake();

        $this->actingAs(User::factory()->admin()->create());
    }

    private function series(): CourseSeries
    {
        return CourseSeries::factory()->create([
            'course_id' => Course::factory()->create()->getKey(),
            'capacity' => 10,
        ]);
    }

    private function lesson(?CourseSeries $series, string $startingIn = '+1 week', array $attributes = []): Lesson
    {
        $start = now()->modify($startingIn);

        return Lesson::factory()->create([
            'series_id' => $series?->getKey(),
            'lesson_date' => $start->format('Y-m-d'),
            'start_time' => $start->format('H:i'),
            'end_time' => $start->copy()->addHour()->format('H:i'),
            ...$attributes,
        ]);
    }

    private function table(Lesson $lesson): Testable
    {
        return Livewire::test(AttendancesRelationManager::class, [
            'ownerRecord' => $lesson,
            'pageClass' => ViewLesson::class,
        ]);
    }

    private function seat(Lesson $lesson): LessonAttendance
    {
        $enrollment = CourseEnrollment::factory()->create([
            'series_id' => $lesson->series_id,
            'status' => CourseEnrollmentStatus::Active,
        ]);

        return LessonAttendance::query()
            ->where('lesson_id', $lesson->getKey())
            ->where('enrollment_id', $enrollment->getKey())
            ->firstOrFail();
    }

    public function test_staff_can_amend_the_note_on_an_absence(): void
    {
        $lesson = $this->lesson($this->series());
        $seat = $this->seat($lesson);

        $seat->update([
            'attended' => false,
            'cancelled_at' => now(),
            'excuse_reason' => LessonExcuseReason::Other,
        ]);

        $this->table($lesson)
            ->callAction(TestAction::make('editExcuse')->table($seat), [
                'excuse_reason' => LessonExcuseReason::Illness->value,
                'excuse_note' => 'Angína, ozve se příští týden.',
            ])
            ->assertHasNoActionErrors();

        $seat->refresh();

        $this->assertSame(LessonExcuseReason::Illness, $seat->excuse_reason);
        $this->assertSame('Angína, ozve se příští týden.', $seat->excuse_note);
        $this->assertNotNull($seat->cancelled_at, 'Amending the note must not put the client back into the lesson.');
    }

    public function test_the_excuse_action_is_hidden_on_somebody_who_is_coming(): void
    {
        $lesson = $this->lesson($this->series());
        $seat = $this->seat($lesson);

        $this->table($lesson)->assertActionHidden(TestAction::make('editExcuse')->table($seat));
    }

    public function test_every_row_links_to_its_own_page(): void
    {
        $lesson = $this->lesson($this->series());
        $seat = $this->seat($lesson);

        $this->table($lesson)->assertActionHasUrl(
            TestAction::make('detail')->table($seat),
            LessonAttendanceResource::getUrl('view', ['record' => $seat]),
        );
    }

    /**
     * The seat of a cancelled sign-up holds no place, so there is nothing to
     * free or reclaim on it.
     */
    public function test_the_presence_switch_is_disabled_on_a_cancelled_sign_up(): void
    {
        $lesson = $this->lesson($this->series());
        $seat = $this->seat($lesson);

        $seat->enrollment->update(['status' => CourseEnrollmentStatus::Cancelled]);

        $this->table($lesson)
            ->filterTable('hide_cancelled', false)
            ->assertActionDisabled(TestAction::make('toggleAttendance')->table($seat->fresh()));
    }

    /**
     * Adding somebody by hand has to leave a sign-up behind it — an account, a
     * payment request, a confirmation e-mail — so it goes through the same flow
     * the public site uses.
     */
    public function test_adding_a_participant_books_a_real_seat(): void
    {
        $lesson = $this->lesson(null, '+1 week', [
            'published_at' => now()->subDay(),
            'capacity' => 10,
            'price' => 500,
        ]);

        $this->table($lesson)
            ->callAction(TestAction::make('addParticipant')->table(), [
                'name' => 'Jana Nováková',
                'email' => 'jana@example.test',
                'phone' => '+420777123456',
            ])
            ->assertHasNoActionErrors();

        $booking = LessonBooking::query()->where('lesson_id', $lesson->getKey())->sole();

        $this->assertSame('jana@example.test', $booking->client->email);
        $this->assertDatabaseHas(LessonAttendance::class, [
            'booking_id' => $booking->getKey(),
            'lesson_id' => $lesson->getKey(),
            'attended' => true,
        ]);
    }

    /**
     * A lesson of a course série is not sold by the seat, so offering to book
     * one there would only ever fail. Moving a client in is the way.
     */
    public function test_adding_a_participant_is_hidden_on_a_lesson_that_is_not_on_sale(): void
    {
        $lesson = $this->lesson($this->series());
        $this->seat($lesson);

        $this->table($lesson)
            ->assertActionHidden(TestAction::make('addParticipant')->table())
            ->assertActionVisible(TestAction::make('moveClientIntoLesson')->table());
    }
}
