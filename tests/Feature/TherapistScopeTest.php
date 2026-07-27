<?php

namespace Tests\Feature;

use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\CourseEnrollmentResource;
use App\Filament\Clusters\Kurzy\Resources\Courses\CourseResource;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\CourseSeriesResource;
use App\Filament\Clusters\Kurzy\Resources\LessonAttendances\LessonAttendanceResource;
use App\Filament\Clusters\Kurzy\Resources\LessonBookings\LessonBookingResource;
use App\Filament\Clusters\Kurzy\Resources\Lessons\LessonResource;
use App\Filament\Clusters\Provoz\Resources\Clients\ClientResource;
use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Filament\Clusters\Provoz\Resources\Users\UserResource;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\Lesson;
use App\Models\LessonAttendance;
use App\Models\LessonBooking;
use App\Models\Reservation;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TherapistScopeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: StaffProfile}
     */
    private function therapistWithProfile(): array
    {
        // The Therapist capability auto-creates the staff profile.
        $user = User::factory()->therapist()->create();

        return [$user, $user->staffProfile];
    }

    public function test_a_therapist_only_sees_their_own_reservations(): void
    {
        [$therapistA, $profileA] = $this->therapistWithProfile();
        [, $profileB] = $this->therapistWithProfile();

        $mine = Reservation::factory()->create(['therapist_id' => $profileA->id]);
        $theirs = Reservation::factory()->create(['therapist_id' => $profileB->id]);

        $this->actingAs($therapistA);

        $ids = ReservationResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));
    }

    /**
     * The client base is deliberately shared — unlike reservations and course
     * offerings, Klienti carries no row scope, so a therapist covering someone
     * else's visit has the client's history in front of them.
     * See {@see StaffUserAccessTest} for the full access rules.
     */
    public function test_a_therapist_sees_every_client_not_only_the_ones_they_treated(): void
    {
        [$therapistA, $profileA] = $this->therapistWithProfile();
        [, $profileB] = $this->therapistWithProfile();

        $treated = User::factory()->customer()->create();
        $aColleaguesClient = User::factory()->customer()->create();

        Reservation::factory()->create(['client_id' => $treated->id, 'therapist_id' => $profileA->id]);
        Reservation::factory()->create(['client_id' => $aColleaguesClient->id, 'therapist_id' => $profileB->id]);

        $this->actingAs($therapistA);

        $ids = ClientResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($ids->contains($treated->id));
        $this->assertTrue($ids->contains($aColleaguesClient->id));
    }

    public function test_a_therapist_only_sees_courses_they_instruct(): void
    {
        [$therapistA] = $this->therapistWithProfile();
        [$therapistB] = $this->therapistWithProfile();

        $mine = Course::factory()->create(['instructor_id' => $therapistA->id]);
        $theirs = Course::factory()->create(['instructor_id' => $therapistB->id]);

        $this->actingAs($therapistA);

        $ids = CourseResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));
    }

    public function test_an_admin_sees_every_reservation_and_client(): void
    {
        $admin = User::factory()->admin()->create();
        [, $profileA] = $this->therapistWithProfile();
        [, $profileB] = $this->therapistWithProfile();

        Reservation::factory()->create(['therapist_id' => $profileA->id]);
        Reservation::factory()->create(['therapist_id' => $profileB->id]);

        $this->actingAs($admin);

        $this->assertSame(2, ReservationResource::getEloquentQuery()->count());
    }

    public function test_an_admin_acting_as_therapist_is_not_scoped(): void
    {
        $admin = User::factory()->admin()->therapist()->create();
        [, $profileOther] = $this->therapistWithProfile();

        Reservation::factory()->create(['therapist_id' => $profileOther->id]);

        $this->actingAs($admin);

        // Admins keep the full view even when they also practise.
        $this->assertSame(1, ReservationResource::getEloquentQuery()->count());
    }

    /**
     * The Tým resource is not row-scoped either: a therapist reads every
     * colleague's record but writes to none of them.
     * See {@see StaffUserAccessTest} for the full access rules.
     */
    public function test_therapists_reach_the_staff_users_resource_read_only(): void
    {
        [$therapist] = $this->therapistWithProfile();
        $admin = User::factory()->admin()->create();
        $colleague = User::factory()->lecturer()->create();

        $this->actingAs($therapist);
        $this->assertFalse(UserResource::canManageStaff());
        $this->assertFalse(UserResource::canEdit($colleague));

        $this->actingAs($admin);
        $this->assertTrue(UserResource::canAccess());
        $this->assertTrue(UserResource::canManageStaff());
    }

    /**
     * Someone who only teaches is scoped by the same `instructor_id` as a
     * therapist who teaches. The Kurzy resources are granted to the `lecturer`
     * role, so without this every course, série, lesson and roster in the clinic
     * was theirs to read.
     */
    public function test_a_lecturer_only_sees_the_offerings_they_instruct(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $colleague = User::factory()->lecturer()->create();

        $mine = Course::factory()->create(['instructor_id' => $lecturer->id]);
        $theirs = Course::factory()->create(['instructor_id' => $colleague->id]);

        $mySeries = CourseSeries::factory()->create(['course_id' => $mine->id]);
        $theirSeries = CourseSeries::factory()->create(['course_id' => $theirs->id]);

        $myLesson = Lesson::factory()->create(['instructor_id' => $lecturer->id, 'series_id' => $mySeries->id]);
        $theirLesson = Lesson::factory()->create(['instructor_id' => $colleague->id, 'series_id' => $theirSeries->id]);

        $myEnrollment = CourseEnrollment::factory()->create(['series_id' => $mySeries->id]);
        $theirEnrollment = CourseEnrollment::factory()->create(['series_id' => $theirSeries->id]);

        $myBooking = LessonBooking::factory()->create(['lesson_id' => $myLesson->id]);
        $theirBooking = LessonBooking::factory()->create(['lesson_id' => $theirLesson->id]);

        $this->actingAs($lecturer);

        foreach ([
            CourseResource::class => [$mine, $theirs],
            CourseSeriesResource::class => [$mySeries, $theirSeries],
            LessonResource::class => [$myLesson, $theirLesson],
            CourseEnrollmentResource::class => [$myEnrollment, $theirEnrollment],
            LessonBookingResource::class => [$myBooking, $theirBooking],
        ] as $resource => [$ours, $theirsRecord]) {
            $ids = $resource::getEloquentQuery()->pluck('id');

            $this->assertTrue($ids->contains($ours->id), "{$resource} hides the lecturer's own record.");
            $this->assertFalse($ids->contains($theirsRecord->id), "{$resource} leaks a colleague's record.");
        }
    }

    public function test_a_lecturer_only_sees_attendance_for_their_own_lessons(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $colleague = User::factory()->lecturer()->create();

        $mySeries = CourseSeries::factory()->create([
            'course_id' => Course::factory()->create(['instructor_id' => $lecturer->id]),
        ]);
        $theirSeries = CourseSeries::factory()->create([
            'course_id' => Course::factory()->create(['instructor_id' => $colleague->id]),
        ]);

        $mine = LessonAttendance::factory()->create([
            'lesson_id' => Lesson::factory()->create(['series_id' => $mySeries->id, 'instructor_id' => $lecturer->id]),
        ]);
        $theirs = LessonAttendance::factory()->create([
            'lesson_id' => Lesson::factory()->create(['series_id' => $theirSeries->id, 'instructor_id' => $colleague->id]),
        ]);

        $this->actingAs($lecturer);

        $ids = LessonAttendanceResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));
    }

    /**
     * A série can name its own lecturer, and that hands them the run: the série,
     * its lessons, its roster — and the course it belongs to, which is the only
     * way in. The course's own instructor keeps all of it too.
     */
    public function test_a_lecturer_assigned_to_a_single_series_sees_that_run(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $owner = User::factory()->lecturer()->create();

        $course = Course::factory()->create(['instructor_id' => $owner->id]);
        $handedOver = CourseSeries::factory()->create(['course_id' => $course->id, 'instructor_id' => $lecturer->id]);
        $ownersOwn = CourseSeries::factory()->create(['course_id' => $course->id]);

        // Taught by the série's owner, so only the série assignment can grant it.
        $lesson = Lesson::factory()->create(['series_id' => $handedOver->id, 'instructor_id' => $owner->id]);
        $othersLesson = Lesson::factory()->create(['series_id' => $ownersOwn->id, 'instructor_id' => $owner->id]);

        $enrollment = CourseEnrollment::factory()->create(['series_id' => $handedOver->id]);
        $othersEnrollment = CourseEnrollment::factory()->create(['series_id' => $ownersOwn->id]);

        $this->actingAs($lecturer);

        $this->assertTrue(CourseResource::getEloquentQuery()->pluck('id')->contains($course->id));

        $seriesIds = CourseSeriesResource::getEloquentQuery()->pluck('id');
        $this->assertTrue($seriesIds->contains($handedOver->id));
        $this->assertFalse($seriesIds->contains($ownersOwn->id));

        $lessonIds = LessonResource::getEloquentQuery()->pluck('id');
        $this->assertTrue($lessonIds->contains($lesson->id));
        $this->assertFalse($lessonIds->contains($othersLesson->id));

        $enrollmentIds = CourseEnrollmentResource::getEloquentQuery()->pluck('id');
        $this->assertTrue($enrollmentIds->contains($enrollment->id));
        $this->assertFalse($enrollmentIds->contains($othersEnrollment->id));

        // The course's instructor is never displaced by the handover.
        $this->actingAs($owner);
        $this->assertSame(2, CourseSeriesResource::getEloquentQuery()->count());
    }

    /**
     * The lecturer of a lesson runs it: they may edit it and everything else the
     * page offers, but deleting one stays with an administrator.
     */
    public function test_a_lecturer_may_edit_but_not_delete_the_offerings_they_lead(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $lecturer = User::factory()->lecturer()->create();
        $series = CourseSeries::factory()->create([
            'course_id' => Course::factory()->create(['instructor_id' => $lecturer->id]),
        ]);
        $lesson = Lesson::factory()->create(['series_id' => $series->id, 'instructor_id' => $lecturer->id]);

        $this->actingAs($lecturer);

        $this->assertTrue($lecturer->can('update', $lesson));
        $this->assertTrue($lecturer->can('update', $series));
        $this->assertFalse($lecturer->can('delete', $lesson));
        $this->assertFalse($lecturer->can('delete', $series));
    }

    public function test_an_admin_who_also_teaches_still_sees_every_course(): void
    {
        $admin = User::factory()->admin()->lecturer()->create();
        $colleague = User::factory()->lecturer()->create();

        Course::factory()->create(['instructor_id' => $admin->id]);
        Course::factory()->create(['instructor_id' => $colleague->id]);

        $this->actingAs($admin);

        $this->assertSame(2, CourseResource::getEloquentQuery()->count());
    }
}
