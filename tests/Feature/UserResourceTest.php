<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        foreach (['ViewAny:User', 'View:User', 'Create:User', 'Update:User', 'Delete:User', 'Impersonate:User'] as $name) {
            Permission::findOrCreate($name);
        }
    }

    public function test_account_type_syncs_matching_shield_role(): void
    {
        $this->assertTrue(User::factory()->admin()->create()->hasRole('super_admin'));
        $this->assertTrue(User::factory()->therapist()->create()->hasRole('therapist'));
        $this->assertFalse(
            User::factory()->customer()->create()->hasAnyRole(['super_admin', 'admin', 'therapist'])
        );
    }

    public function test_therapist_without_permission_cannot_view_users_list(): void
    {
        $therapist = User::factory()->therapist()->create();

        $this->actingAs($therapist)->get('/admin/users')->assertForbidden();
    }

    public function test_admin_can_view_users_list(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/users')->assertSuccessful();
    }

    public function test_user_can_be_created_with_account_type_and_direct_permission(): void
    {
        $permission = Permission::findOrCreate('View:User');

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Nový Uživatel',
                'email' => 'novy@example.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => UserRole::Therapist->value,
                'permissions' => [$permission->getKey()],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::where('email', 'novy@example.test')->first();

        $this->assertNotNull($created);
        $this->assertTrue($created->hasRole('therapist'));          // synced from Role
        $this->assertTrue($created->hasPermissionTo('View:User'));  // direct permission
        $this->assertTrue(Hash::check('password123', $created->password));
    }

    public function test_create_user_requires_matching_password_confirmation(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Nesoulad',
                'email' => 'nesoulad@example.test',
                'password' => 'password123',
                'password_confirmation' => 'jine-heslo',
                'role' => UserRole::Therapist->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['password']);
    }

    public function test_create_user_validates_required_fields(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => null,
                'email' => 'not-an-email',
                'password' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'name' => 'required',
                'email' => 'email',
                'password' => 'required',
            ]);
    }

    public function test_edit_page_exposes_impersonate_and_password_reset_actions(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $target = User::factory()->therapist()->create();

        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $target->getKey()])
            ->assertActionExists('impersonate')
            ->callAction('sendPasswordReset');

        Notification::assertSentTo($target, ResetPassword::class);
    }

    public function test_impersonate_action_requires_permission(): void
    {
        // Super admins bypass all gates.
        $this->assertTrue(User::factory()->admin()->create()->canImpersonate());

        // Therapists have no impersonate permission by default.
        $therapist = User::factory()->therapist()->create();
        $this->assertFalse($therapist->canImpersonate());

        // Granting the permission enables it.
        $therapist->givePermissionTo('Impersonate:User');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertTrue($therapist->fresh()->canImpersonate());
    }
}
