<?php

namespace Tests\Feature\Kurzy;

use App\Enums\BookingStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Filament\Clusters\Kurzy\Resources\Lessons\Pages\ViewLesson;
use App\Filament\Clusters\Kurzy\Resources\Lessons\RelationManagers\AttendancesRelationManager;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\Lesson;
use App\Models\LessonAttendance;
use App\Models\LessonBooking;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Docházka section of a lesson: who it lists, who it deliberately leaves
 * out, and what each row says about them.
 */
class LessonAttendanceTableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());
    }

    private function series(): CourseSeries
    {
        return CourseSeries::factory()->create([
            'course_id' => Course::factory()->create()->getKey(),
            'capacity' => 10,
        ]);
    }

    private function lesson(CourseSeries $series, string $startingIn = '+1 week'): Lesson
    {
        $start = now()->modify($startingIn);

        return Lesson::factory()->create([
            'series_id' => $series->getKey(),
            'lesson_date' => $start->format('Y-m-d'),
            'start_time' => $start->format('H:i'),
            'end_time' => $start->copy()->addHour()->format('H:i'),
        ]);
    }

    private function enroll(CourseSeries $series, CourseEnrollmentStatus $status = CourseEnrollmentStatus::Active): CourseEnrollment
    {
        return CourseEnrollment::factory()->create([
            'series_id' => $series->getKey(),
            'status' => $status,
        ]);
    }

    private function seat(Lesson $lesson, CourseEnrollment $enrollment): LessonAttendance
    {
        return LessonAttendance::query()
            ->where('lesson_id', $lesson->getKey())
            ->where('enrollment_id', $enrollment->getKey())
            ->firstOrFail();
    }

    private function table(Lesson $lesson): Testable
    {
        return Livewire::test(AttendancesRelationManager::class, [
            'ownerRecord' => $lesson,
            'pageClass' => ViewLesson::class,
        ]);
    }

    /**
     * A client whose enrollment was cancelled — by staff or by the unpaid-hold
     * sweep — is no longer coming, and a list of who is coming must not name
     * them. The row survives for history behind the filter.
     */
    public function test_a_cancelled_enrollment_is_hidden_until_asked_for(): void
    {
        $series = $this->series();
        $lesson = $this->lesson($series);

        $active = $this->enroll($series);
        $cancelled = $this->enroll($series);

        $stale = $this->seat($lesson, $cancelled);
        $cancelled->update(['status' => CourseEnrollmentStatus::Cancelled]);

        $this->table($lesson)
            ->assertCanSeeTableRecords([$this->seat($lesson, $active)])
            ->assertCanNotSeeTableRecords([$stale])
            ->filterTable('hide_cancelled', false)
            ->assertCanSeeTableRecords([$stale]);
    }

    public function test_a_cancelled_booking_is_hidden_too(): void
    {
        $series = $this->series();
        $lesson = $this->lesson($series);

        $booking = LessonBooking::factory()->create([
            'lesson_id' => $lesson->getKey(),
            'status' => BookingStatus::Confirmed,
        ]);

        $seat = LessonAttendance::query()->where('booking_id', $booking->getKey())->sole();

        $this->table($lesson)->assertCanSeeTableRecords([$seat]);

        // Cancelling normally unseats the row; this one is kept deliberately so
        // the list itself has to decide not to show it.
        LessonBooking::withoutEvents(fn () => $booking->update(['status' => BookingStatus::Cancelled]));

        $this->table($lesson)
            ->assertCanNotSeeTableRecords([$seat])
            ->filterTable('hide_cancelled', false)
            ->assertCanSeeTableRecords([$seat]);
    }

    public function test_a_drop_in_row_names_its_client_and_its_origin(): void
    {
        $series = $this->series();
        $lesson = $this->lesson($series);

        $booking = LessonBooking::factory()->create([
            'lesson_id' => $lesson->getKey(),
            'status' => BookingStatus::Confirmed,
        ]);

        $seat = LessonAttendance::query()->where('booking_id', $booking->getKey())->sole();

        $this->table($lesson)
            ->assertTableColumnStateSet('client.name', $booking->client->name, $seat)
            ->assertTableColumnStateSet('origin', 'Jednorázový vstup', $seat);
    }

    /**
     * A substitute's own série is the one thing the row cannot infer from the
     * lesson — it is why they are a guest here — so the cell names it next to
     * the word that says they are a guest at all.
     */
    public function test_the_series_column_names_the_run_a_substitute_came_from(): void
    {
        $home = $this->series();
        $host = $this->series();

        $lesson = $this->lesson($host);
        $guest = $this->enroll($home);

        $seat = LessonAttendance::create([
            'enrollment_id' => $guest->getKey(),
            'lesson_id' => $lesson->getKey(),
            'attended' => true,
        ]);

        $this->table($lesson)
            ->assertTableColumnStateSet('origin', 'Náhrada · '.$home->name, $seat);
    }

    /**
     * A member of this lesson's own série is the ordinary case: their row says
     * which run they signed up for and nothing else.
     */
    public function test_a_course_seat_names_its_series_without_a_label(): void
    {
        $series = $this->series();
        $lesson = $this->lesson($series);

        $member = $this->enroll($series);

        $this->table($lesson)
            ->assertTableColumnStateSet('origin', $series->name, $this->seat($lesson, $member));
    }

    public function test_the_origin_filter_separates_course_seats_from_guests(): void
    {
        $home = $this->series();
        $host = $this->series();

        $lesson = $this->lesson($host);

        $member = $this->enroll($host);
        $guest = $this->enroll($home);

        $guestSeat = LessonAttendance::create([
            'enrollment_id' => $guest->getKey(),
            'lesson_id' => $lesson->getKey(),
            'attended' => true,
        ]);

        $this->table($lesson)
            ->filterTable('origin', 'course')
            ->assertCanSeeTableRecords([$this->seat($lesson, $member)])
            ->assertCanNotSeeTableRecords([$guestSeat])
            ->filterTable('origin', 'substitute')
            ->assertCanSeeTableRecords([$guestSeat])
            ->assertCanNotSeeTableRecords([$this->seat($lesson, $member)]);
    }

    public function test_the_presence_filter_separates_the_excused(): void
    {
        $series = $this->series();
        $lesson = $this->lesson($series);

        $coming = $this->enroll($series);
        $excused = $this->enroll($series);

        $this->seat($lesson, $excused)->update(['attended' => false, 'cancelled_at' => now()]);

        $this->table($lesson)
            ->filterTable('presence', 'absent')
            ->assertCanSeeTableRecords([$this->seat($lesson, $excused)])
            ->assertCanNotSeeTableRecords([$this->seat($lesson, $coming)])
            ->filterTable('presence', 'present')
            ->assertCanSeeTableRecords([$this->seat($lesson, $coming)])
            ->assertCanNotSeeTableRecords([$this->seat($lesson, $excused)]);
    }

    /**
     * The tally counts lessons that have already been held, so an excused past
     * lesson pulls it below the total while future ones do not count at all.
     */
    public function test_the_tally_counts_held_lessons_only(): void
    {
        $series = $this->series();

        $first = $this->lesson($series, '-2 weeks');
        $second = $this->lesson($series, '-1 week');
        $this->lesson($series, '+1 week');

        $enrollment = $this->enroll($series);

        $this->seat($first, $enrollment)->update(['attended' => false, 'cancelled_at' => now()]);

        $this->table($second)
            ->assertTableColumnStateSet('series_tally', '1/2', $this->seat($second, $enrollment));
    }

    public function test_a_standalone_lesson_has_no_tally_column(): void
    {
        $lesson = Lesson::factory()->create(['series_id' => null]);

        $booking = LessonBooking::factory()->create([
            'lesson_id' => $lesson->getKey(),
            'status' => BookingStatus::Confirmed,
        ]);

        $this->table($lesson)
            ->assertCanSeeTableRecords([LessonAttendance::query()->where('booking_id', $booking->getKey())->sole()])
            ->assertTableColumnHidden('series_tally');
    }
}
