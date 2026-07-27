<?php

namespace Tests\Feature;

use App\Enums\CourseEnrollmentStatus;
use App\Filament\Clusters\Kurzy\Resources\Lessons\Pages\ListLessons;
use App\Filament\Clusters\Kurzy\Resources\Lessons\Pages\ViewLesson;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\Lesson;
use App\Models\LessonAttendance;
use App\Models\User;
use App\Support\Substitutes\SubstituteOptions;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A lesson borrows its série's capacity and counts the people actually expected
 * in the room: everyone enrolled, minus those excused from that date, plus
 * substitutes moved in from another série. Staff read it off the occupancy bar to
 * see whether a free spot can be offered to somebody else.
 */
class LessonOccupancyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());
    }

    private function seriesWithEnrollments(int $capacity, int $enrolled): CourseSeries
    {
        $series = CourseSeries::factory()->create(['capacity' => $capacity]);

        CourseEnrollment::factory()->count($enrolled)->create([
            'series_id' => $series->getKey(),
            'status' => CourseEnrollmentStatus::Active,
        ]);

        return $series;
    }

    /**
     * Excusing somebody the way the app does it — the roster row already exists.
     */
    private function excuse(CourseEnrollment $enrollment, Lesson $lesson): void
    {
        LessonAttendance::updateOrCreate(
            ['enrollment_id' => $enrollment->getKey(), 'lesson_id' => $lesson->getKey()],
            ['attended' => false, 'cancelled_at' => now()],
        );
    }

    public function test_lesson_borrows_the_series_capacity(): void
    {
        $lesson = Lesson::factory()->create([
            'series_id' => CourseSeries::factory()->create(['capacity' => 12]),
        ]);

        $this->assertSame(12, $lesson->capacity);
    }

    public function test_taken_spots_counts_the_active_enrollments(): void
    {
        $series = $this->seriesWithEnrollments(capacity: 10, enrolled: 4);

        CourseEnrollment::factory()->create([
            'series_id' => $series->getKey(),
            'status' => CourseEnrollmentStatus::Cancelled,
        ]);

        $lesson = Lesson::factory()->create(['series_id' => $series->getKey()]);

        $this->assertSame(4, $lesson->takenSpots());
        $this->assertSame(6, $lesson->spotsLeft());
    }

    public function test_an_excused_client_frees_a_spot_on_that_lesson_only(): void
    {
        $series = $this->seriesWithEnrollments(capacity: 10, enrolled: 4);
        $lesson = Lesson::factory()->create(['series_id' => $series->getKey()]);
        $other = Lesson::factory()->create(['series_id' => $series->getKey()]);

        $this->excuse($series->enrollments()->first(), $lesson);

        $this->assertSame(3, $lesson->fresh()->takenSpots());
        $this->assertSame(4, $other->fresh()->takenSpots());
    }

    public function test_a_substitute_from_another_series_takes_a_spot(): void
    {
        $series = $this->seriesWithEnrollments(capacity: 10, enrolled: 4);
        $lesson = Lesson::factory()->create(['series_id' => $series->getKey()]);

        $foreignEnrollment = CourseEnrollment::factory()->create([
            'series_id' => CourseSeries::factory()->create()->getKey(),
            'status' => CourseEnrollmentStatus::Active,
        ]);

        LessonAttendance::factory()->create([
            'enrollment_id' => $foreignEnrollment->getKey(),
            'lesson_id' => $lesson->getKey(),
            'cancelled_at' => null,
            'attended' => false,
        ]);

        $this->assertSame(5, $lesson->fresh()->takenSpots());
        $this->assertSame(1, $lesson->fresh()->substitutesInCount());
    }

    /**
     * Occupancy counts the presence list, so it can never fall below the people
     * actually coming: stale rows belonging to clients who have since left the
     * série are cancelled and simply do not count, rather than being subtracted
     * from the ones who remain.
     */
    public function test_stale_cancelled_rows_do_not_drag_the_count_down(): void
    {
        $series = $this->seriesWithEnrollments(capacity: 10, enrolled: 1);
        $lesson = Lesson::factory()->create(['series_id' => $series->getKey()]);

        LessonAttendance::factory()->count(2)->create([
            'lesson_id' => $lesson->getKey(),
            'cancelled_at' => now(),
            'attended' => false,
        ]);

        // One enrolled client is still expected; the two cancelled strangers are not.
        $this->assertSame(1, $lesson->fresh()->takenSpots());
        $this->assertSame(9, $lesson->fresh()->spotsLeft());
    }

    /**
     * A client whose enrollment was cancelled keeps their row for history, but
     * stops holding a seat.
     */
    public function test_a_cancelled_enrollment_stops_taking_up_a_place(): void
    {
        $series = $this->seriesWithEnrollments(capacity: 10, enrolled: 2);
        $lesson = Lesson::factory()->create(['series_id' => $series->getKey()]);

        $this->assertSame(2, $lesson->fresh()->takenSpots());

        $series->enrollments()->first()->update(['status' => CourseEnrollmentStatus::Cancelled]);

        $this->assertSame(1, $lesson->fresh()->takenSpots());
        $this->assertSame(2, $lesson->attendances()->count(), 'The row stays for history.');
    }

    public function test_eager_loaded_counts_match_the_live_ones(): void
    {
        $series = $this->seriesWithEnrollments(capacity: 10, enrolled: 4);
        $lesson = Lesson::factory()->create(['series_id' => $series->getKey()]);

        $this->excuse($series->enrollments()->first(), $lesson);

        $foreignEnrollment = CourseEnrollment::factory()->create([
            'series_id' => CourseSeries::factory()->create()->getKey(),
            'status' => CourseEnrollmentStatus::Active,
        ]);

        LessonAttendance::factory()->create([
            'enrollment_id' => $foreignEnrollment->getKey(),
            'lesson_id' => $lesson->getKey(),
            'cancelled_at' => null,
            'attended' => false,
        ]);

        $eager = Lesson::query()->withOccupancyCounts()->findOrFail($lesson->getKey());

        $this->assertSame(1, (int) $eager->excused_count);
        $this->assertSame(1, (int) $eager->substitutes_in_count);
        $this->assertSame($lesson->fresh()->takenSpots(), $eager->takenSpots());
    }

    public function test_the_substitute_engine_reads_the_same_free_spots(): void
    {
        $series = $this->seriesWithEnrollments(capacity: 10, enrolled: 4);
        $lesson = Lesson::factory()->create(['series_id' => $series->getKey()]);

        $this->assertSame($lesson->spotsLeft(), (new SubstituteOptions)->freeSpots($lesson));
    }

    public function test_the_bar_renders_free_spots_out_of_total_in_the_lesson_table(): void
    {
        $series = $this->seriesWithEnrollments(capacity: 10, enrolled: 4);
        $lesson = Lesson::factory()->create(['series_id' => $series->getKey()]);

        Livewire::test(ListLessons::class)
            ->assertCanSeeTableRecords([$lesson])
            ->assertSee('6/10');
    }

    public function test_the_lesson_detail_page_breaks_the_occupancy_down(): void
    {
        $series = $this->seriesWithEnrollments(capacity: 10, enrolled: 4);
        $lesson = Lesson::factory()->create(['series_id' => $series->getKey()]);

        Livewire::test(ViewLesson::class, ['record' => $lesson->getKey()])
            ->assertSee('Obsazenost')
            ->assertSee('Přihlášeno')
            ->assertSee('Náhradníci')
            ->assertSee('6/10');
    }
}
