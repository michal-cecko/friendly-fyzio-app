<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

/**
 * Replaces the single `users.role` enum + `acts_as_therapist` boolean with
 * composable capabilities stored as Spatie roles (see App\Enums\Capability):
 *
 *   admin     → super_admin  (+ therapist when acts_as_therapist was set)
 *   therapist → therapist
 *   customer  → customer      (an identity role, kept independent of capabilities)
 *
 * The roles were already partly synced by the old User::booted() hook; this
 * makes them authoritative and back-fills the customer identity, then drops the
 * columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        $superAdmin = config('filament-shield.super_admin.name', 'super_admin');
        $morph = (new User)->getMorphClass();

        foreach (['therapist', 'lecturer', User::CUSTOMER_ROLE, $superAdmin] as $role) {
            Role::findOrCreate($role);
        }

        foreach (DB::table('users')->select('id', 'role', 'acts_as_therapist')->cursor() as $user) {
            $roles = match ($user->role) {
                'admin' => $user->acts_as_therapist ? [$superAdmin, 'therapist'] : [$superAdmin],
                'therapist' => ['therapist'],
                'customer' => [User::CUSTOMER_ROLE],
                default => [],
            };

            foreach (Role::query()->whereIn('name', $roles)->pluck('id') as $roleId) {
                DB::table('model_has_roles')->updateOrInsert([
                    'role_id' => $roleId,
                    'model_type' => $morph,
                    'model_id' => $user->id,
                ]);
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'acts_as_therapist']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('customer')->after('password');
            $table->boolean('acts_as_therapist')->default(false)->after('role');
        });

        $superAdmin = config('filament-shield.super_admin.name', 'super_admin');

        foreach (DB::table('users')->select('id')->cursor() as $user) {
            $roles = DB::table('roles')
                ->join('model_has_roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_id', $user->id)
                ->pluck('roles.name')
                ->all();

            $role = match (true) {
                in_array($superAdmin, $roles, true) => 'admin',
                in_array('therapist', $roles, true) => 'therapist',
                default => 'customer',
            };

            DB::table('users')->where('id', $user->id)->update([
                'role' => $role,
                'acts_as_therapist' => $role === 'admin' && in_array('therapist', $roles, true),
            ]);
        }
    }
};
