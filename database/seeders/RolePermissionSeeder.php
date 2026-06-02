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

        $superAdmin = Role::firstOrCreate(['name' => config('filament-shield.super_admin.name', 'super_admin')]);
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $therapist = Role::firstOrCreate(['name' => 'therapist']);

        $admin->syncPermissions(Permission::all());
        $therapist->syncPermissions(Permission::whereIn('name', ['View:MediaLibrary'])->get());

        // super_admin needs no explicit permissions — it bypasses every check via Gate::before.
    }
}
