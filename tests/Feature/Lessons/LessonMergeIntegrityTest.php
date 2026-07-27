<?php

namespace Tests\Feature\Lessons;

use App\Enums\BookingStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\Lesson;
use App\Models\LessonAttendance;
use App\Models\LessonBooking;
use App\Models\User;
use App\Support\Substitutes\ExcuseFromLesson;
use App\Support\Substitutes\RestoreLessonAttendance;
use App\Support\Substitutes\SubstituteException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The structural promises the CourseLesson/OneOffEvent merge rests on: one
 * table, one occupancy number, and the constraints the substitute engine
 * depends on surviving the foreign-key repoint.
 */
class LessonMergeIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_two_old_tables_are_gone_and_everything_points_at_lessons(): void
    {
        $this->assertFalse(Schema::hasTable('course_lessons'));
        $this->assertFalse(Schema::hasTable('one_off_events'));
        $this->assertFalse(Schema::hasTable('one_off_event_bookings'));

        $this->assertTrue(Schema::hasTable('lessons'));
        $this->assertTrue(Schema::hasColumn('lessons', 'series_id'));
        $this->assertTrue(Schema::hasColumn('lessons', 'released_at'));
        $this->assertTrue(Schema::hasColumn('lesson_bookings', 'lesson_id'));
        $this->assertTrue(Schema::hasColumn('courses', 'drop_in_price'));
    }

    /**
     * SQLite rebuilds a table to repoint a foreign key, which is exactly where
     * indexes go missing. "A client can only be on a lesson's presence list
     * once" has to survive that.
     */
    public function test_the_attendance_pair_is_still_unique(): void
    {
        $unique = collect(Schema::getIndexes('lesson_attendances'))
            ->contains(fn (array $index): bool => $index['unique']
                && count($index['columns']) === 2
                && in_array('client_id', $index['columns'], true)
                && in_array('lesson_id', $index['columns'], true));

        $this->assertTrue($unique, 'lesson_attendances lost its unique (client_id, lesson_id) index.');
    }

    public function test_a_standalone_lesson_counts_only_its_bookings(): void
    {
        $lesson = Lesson::factory()->standalone()->create(['capacity' => 5]);

        $this->assertSame(0, $lesson->takenSpots());

        LessonBooking::factory()->for($lesson, 'lesson')->create([
            'client_id' => User::factory()->customer()->create()->getKey(),
            'status' => BookingStatus::Confirmed,
        ]);

        $this->assertSame(1, $lesson->fresh()->takenSpots());
        $this->assertSame(4, $lesson->fresh()->spotsLeft());
    }

    public function test_a_series_lesson_borrows_capacity_and_price(): void
    {
        $course = Course::factory()->create(['drop_in_price' => 260]);
        $series = CourseSeries::factory()->for($course)->create(['capacity' => 8]);
        $lesson = Lesson::factory()->for($series, 'series')->create();

        $this->assertSame(8, $lesson->capacity);
        $this->assertSame(260, $lesson->price);
        $this->assertTrue($lesson->isPartOfSeries());
    }

    public function test_a_course_that_does_not_sell_seats_prices_nothing(): void
    {
        $series = CourseSeries::factory()->for(Course::factory()->create(['drop_in_price' => null]))->create();

        $this->assertNull(Lesson::factory()->for($series, 'series')->create()->price);
    }

    /**
     * Buying a seat puts you on the presence list, which is what lets a lecturer
     * tick a drop-in off at the door.
     */
    public function test_buying_a_seat_puts_you_on_the_presence_list(): void
    {
        $lesson = Lesson::factory()->standalone()->create(['capacity' => 5]);
        $client = User::factory()->customer()->create();

        $booking = LessonBooking::factory()->for($lesson, 'lesson')->create([
            'client_id' => $client->getKey(),
            'status' => BookingStatus::Confirmed,
        ]);

        $seat = LessonAttendance::query()
            ->where('lesson_id', $lesson->getKey())
            ->where('client_id', $client->getKey())
            ->sole();

        $this->assertSame($booking->getKey(), $seat->booking_id);
        $this->assertNull($seat->enrollment_id);
        $this->assertTrue($seat->isDropIn());
        $this->assertFalse($seat->isSubstituteGuest());

        // Cancelling takes the seat back.
        $booking->update(['status' => BookingStatus::Cancelled]);

        $this->assertSame(0, $lesson->fresh()->takenSpots());
        $this->assertDatabaseMissing('lesson_attendances', ['id' => $seat->getKey()]);
    }

    /**
     * A course participant who also buys a drop-in elsewhere must not end up
     * seated twice on the same lesson — the rule the old index could not reach.
     */
    public function test_a_client_can_only_hold_one_seat_per_lesson(): void
    {
        $series = CourseSeries::factory()->create(['capacity' => 5]);
        $lesson = Lesson::factory()->for($series, 'series')->create();

        $enrollment = CourseEnrollment::factory()->create([
            'series_id' => $series->getKey(),
            'status' => CourseEnrollmentStatus::Active,
        ]);

        LessonBooking::factory()->for($lesson, 'lesson')->create([
            'client_id' => $enrollment->client_id,
            'status' => BookingStatus::Confirmed,
        ]);

        $this->assertSame(1, LessonAttendance::query()
            ->where('lesson_id', $lesson->getKey())
            ->where('client_id', $enrollment->client_id)
            ->count());

        $this->assertSame(1, $lesson->fresh()->takenSpots());
    }

    /**
     * The seat an excused client freed can be bought by somebody else. Once it
     * has been, taking it back would put the room over capacity.
     */
    public function test_an_excused_client_cannot_be_restored_once_the_seat_was_sold(): void
    {
        Notification::fake();

        $course = Course::factory()->create(['drop_in_price' => 260, 'early_cancel_hours' => 24, 'max_substitutions' => 0]);
        $series = CourseSeries::factory()->for($course)->create(['capacity' => 1]);
        $start = now()->addWeek();
        $lesson = Lesson::factory()->for($series, 'series')->create([
            'lesson_date' => $start->format('Y-m-d'),
            'start_time' => $start->format('H:i'),
            'end_time' => $start->copy()->addHour()->format('H:i'),
        ]);

        $enrollment = CourseEnrollment::factory()->create([
            'series_id' => $series->getKey(),
            'status' => CourseEnrollmentStatus::Active,
        ]);

        app(ExcuseFromLesson::class)($enrollment, $lesson, allowToken: false, notifyClient: false);

        LessonBooking::factory()->for($lesson, 'lesson')->create([
            'client_id' => User::factory()->customer()->create()->getKey(),
            'status' => BookingStatus::Confirmed,
        ]);

        $attendance = LessonAttendance::query()
            ->where('enrollment_id', $enrollment->getKey())
            ->where('lesson_id', $lesson->getKey())
            ->sole();

        $this->expectException(SubstituteException::class);
        $this->expectExceptionMessage('jednorázový vstup');

        app(RestoreLessonAttendance::class)($attendance);
    }
}
