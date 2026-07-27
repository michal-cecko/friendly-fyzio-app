<?php

namespace Tests\Feature;

use App\Enums\CourseEnrollmentStatus;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\Lesson;
use App\Models\LessonAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * The presence list is a real roster, not an exception log: every active
 * enrollment holds a row for every lesson of its série, created as soon as either
 * side appears. Staff open a lesson and see who is supposed to be there.
 */
class LessonRosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_lesson_gets_a_row_for_every_active_enrollment(): void
    {
        $series = CourseSeries::factory()->create(['capacity' => 10]);

        CourseEnrollment::factory()->count(3)->create([
            'series_id' => $series->getKey(),
            'status' => CourseEnrollmentStatus::Active,
        ]);

        CourseEnrollment::factory()->create([
            'series_id' => $series->getKey(),
            'status' => CourseEnrollmentStatus::Waitlist,
        ]);

        $lesson = Lesson::factory()->create(['series_id' => $series->getKey()]);

        $this->assertSame(3, $lesson->attendances()->count());
    }

    public function test_a_new_enrollment_gets_a_row_for_every_lesson(): void
    {
        $series = CourseSeries::factory()->create(['capacity' => 10]);
        Lesson::factory()->count(4)->create(['series_id' => $series->getKey()]);

        $enrollment = CourseEnrollment::factory()->create([
            'series_id' => $series->getKey(),
            'status' => CourseEnrollmentStatus::Active,
        ]);

        $this->assertSame(4, $enrollment->attendances()->count());
    }

    public function test_a_waitlisted_enrollment_stays_off_the_roster_until_promoted(): void
    {
        $series = CourseSeries::factory()->create(['capacity' => 10]);
        Lesson::factory()->count(2)->create(['series_id' => $series->getKey()]);

        $enrollment = CourseEnrollment::factory()->create([
            'series_id' => $series->getKey(),
            'status' => CourseEnrollmentStatus::Waitlist,
        ]);

        $this->assertSame(0, $enrollment->attendances()->count());

        $enrollment->update(['status' => CourseEnrollmentStatus::Active]);

        $this->assertSame(2, $enrollment->fresh()->attendances()->count());
    }

    public function test_cancelling_keeps_the_rows_but_frees_the_spots(): void
    {
        $series = CourseSeries::factory()->create(['capacity' => 10]);
        $lesson = Lesson::factory()->create(['series_id' => $series->getKey()]);

        $enrollment = CourseEnrollment::factory()->create([
            'series_id' => $series->getKey(),
            'status' => CourseEnrollmentStatus::Active,
        ]);

        $this->assertSame(1, $lesson->fresh()->takenSpots());

        $enrollment->update(['status' => CourseEnrollmentStatus::Cancelled]);

        $this->assertSame(1, $enrollment->fresh()->attendances()->count());
        $this->assertSame(0, $lesson->fresh()->takenSpots());
    }

    public function test_generated_rows_are_not_written_to_the_activity_log(): void
    {
        $series = CourseSeries::factory()->create(['capacity' => 10]);
        Lesson::factory()->count(2)->create(['series_id' => $series->getKey()]);

        CourseEnrollment::factory()->create([
            'series_id' => $series->getKey(),
            'status' => CourseEnrollmentStatus::Active,
        ]);

        $this->assertSame(
            0,
            Activity::query()->where('subject_type', LessonAttendance::class)->count(),
        );
    }

    public function test_the_backfill_command_is_idempotent_and_preserves_excuses(): void
    {
        $series = CourseSeries::factory()->create(['capacity' => 10]);
        $lesson = Lesson::factory()->create(['series_id' => $series->getKey()]);
        $enrollment = CourseEnrollment::factory()->create([
            'series_id' => $series->getKey(),
            'status' => CourseEnrollmentStatus::Active,
        ]);

        $excused = $enrollment->attendances()->first();
        $excused->update(['cancelled_at' => now()]);

        // A série whose rows predate the roster.
        LessonAttendance::query()->whereKeyNot($excused->getKey())->delete();
        $olderSeries = CourseSeries::factory()->create(['capacity' => 10]);
        Lesson::factory()->count(3)->create(['series_id' => $olderSeries->getKey()]);
        $olderEnrollment = CourseEnrollment::factory()->create([
            'series_id' => $olderSeries->getKey(),
            'status' => CourseEnrollmentStatus::Active,
        ]);
        $olderEnrollment->attendances()->delete();

        $this->artisan('lessons:backfill-attendance')->assertSuccessful();

        $this->assertSame(3, $olderEnrollment->fresh()->attendances()->count());
        $this->assertNotNull($excused->fresh()->cancelled_at);

        $before = LessonAttendance::query()->count();
        $this->artisan('lessons:backfill-attendance')->assertSuccessful();

        $this->assertSame($before, LessonAttendance::query()->count());
        $this->assertNotNull($excused->fresh()->cancelled_at);
        $this->assertSame(1, $lesson->fresh()->attendances()->count());
    }
}
