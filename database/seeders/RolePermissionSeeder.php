<?php

namespace Database\Seeders;

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
        Artisan::call('shield:generate', [
            '--all' => true,
            '--panel' => 'admin',
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

        $admin->syncPermissions(Permission::all());
        $therapist->syncPermissions(Permission::whereIn('name', [
            'View:MediaLibrary',
            'View:Calendar',
            'ViewAny:Service', 'View:Service',
            'ViewAny:ServiceCategory', 'View:ServiceCategory',
            'ViewAny:Room', 'View:Room',
            'ViewAny:Building', 'View:Building',
            'ViewAny:ClientNote', 'View:ClientNote', 'Create:ClientNote', 'Update:ClientNote', 'Delete:ClientNote',
            // Read-only access to the courses / workshops / one-time lessons schedule.
            'ViewAny:Course', 'View:Course',
            'ViewAny:CourseCategory', 'View:CourseCategory',
            'ViewAny:CourseSeries', 'View:CourseSeries',
            'ViewAny:CourseLesson', 'View:CourseLesson',
            'ViewAny:Workshop', 'View:Workshop',
            'ViewAny:OneTimeLesson', 'View:OneTimeLesson',
        ])->get());

        // super_admin needs no explicit permissions — it bypasses every check via Gate::before.
    }
}
