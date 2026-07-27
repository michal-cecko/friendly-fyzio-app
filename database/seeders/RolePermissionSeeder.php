<?php

namespace Database\Seeders;

use App\Enums\Capability;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Regenerate all Shield permissions so `migrate:fresh --seed` stays reproducible
        // (permissions are database rows that a fresh migration wipes).
        //
        // --option is passed explicitly: without it shield:generate asks what to
        // generate. On the CLI the prompt silently takes its default, but under
        // test the mocked console throws, and an unattended deploy should never
        // depend on a prompt's default in the first place.
        Artisan::call('shield:generate', [
            '--all' => true,
            '--panel' => 'admin',
            '--option' => 'policies_and_permissions',
            '--no-interaction' => true,
        ]);

        // Custom permission backing the impersonate action (see User::canImpersonate()).
        Permission::findOrCreate('Impersonate:User');

        // ClientNote has no Filament resource, so shield:generate does not cover it.
        foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete'] as $action) {
            Permission::findOrCreate("{$action}:ClientNote");
        }

        $superAdmin = Role::firstOrCreate(['name' => config('filament-shield.super_admin.name', 'super_admin')]);
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $therapist = Role::firstOrCreate(['name' => 'therapist']);
        $lecturer = Role::firstOrCreate(['name' => 'lecturer']);
        // Carries no permissions of its own — it only unlocks the aggregate
        // money figures (see User::canViewRevenue()).
        Role::firstOrCreate(['name' => Capability::Revenue->roleName()]);
        // The `customer` role is an identity marker, not a panel role — it carries
        // no permissions (customers never reach the admin panel).
        Role::firstOrCreate(['name' => User::CUSTOMER_ROLE]);

        $admin->syncPermissions(Permission::all());

        // Lecturers manage the courses/events they instruct (rows scoped to
        // their instructor_id via ScopedToTherapist's lecturer branch).
        $lecturer->syncPermissions(Permission::whereIn('name', [
            'View:MediaLibrary',
            'ViewAny:Course', 'View:Course',
            'ViewAny:CourseCategory', 'View:CourseCategory',
            'ViewAny:CourseSeries', 'View:CourseSeries',
            'ViewAny:Lesson', 'View:Lesson',
            'ViewAny:CourseEnrollment', 'View:CourseEnrollment',
            'ViewAny:LessonAttendance', 'View:LessonAttendance', 'Create:LessonAttendance', 'Update:LessonAttendance',
            'ViewAny:Lesson', 'View:Lesson',
            'ViewAny:LessonBooking', 'View:LessonBooking',
            'ViewAny:EventCategory', 'View:EventCategory',
        ])->get());
        $therapist->syncPermissions(Permission::whereIn('name', [
            'View:MediaLibrary',
            'View:Calendar',
            'ViewAny:Service', 'View:Service',
            'ViewAny:ServiceCategory', 'View:ServiceCategory',
            'ViewAny:Room', 'View:Room',
            'ViewAny:Building', 'View:Building',
            'ViewAny:ClientNote', 'View:ClientNote', 'Create:ClientNote', 'Update:ClientNote', 'Delete:ClientNote',
            // Their own clients (ClientResource shares the User model; UserResource
            // in the System cluster is gated to admins via canAccess()). Rows are
            // scoped to clients they've treated — see ScopedToTherapist.
            'ViewAny:User', 'View:User', 'Update:User',
            // Their own reservations + recording payments after a visit.
            'ViewAny:Reservation', 'View:Reservation', 'Update:Reservation',
            'ViewAny:Payment', 'View:Payment', 'Create:Payment',
            // Courses / one-off events they instruct, plus enrollment
            // rosters and attendance marking (all row-scoped to their offerings).
            'ViewAny:Course', 'View:Course',
            'ViewAny:CourseCategory', 'View:CourseCategory',
            'ViewAny:CourseSeries', 'View:CourseSeries',
            'ViewAny:Lesson', 'View:Lesson',
            'ViewAny:CourseEnrollment', 'View:CourseEnrollment',
            'ViewAny:LessonAttendance', 'View:LessonAttendance', 'Create:LessonAttendance', 'Update:LessonAttendance',
            'ViewAny:Lesson', 'View:Lesson',
            'ViewAny:EventCategory', 'View:EventCategory',
        ])->get());

        // super_admin needs no explicit permissions — it bypasses every check via Gate::before.
    }
}
