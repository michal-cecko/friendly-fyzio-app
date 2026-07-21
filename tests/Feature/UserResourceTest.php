<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Clusters\System\Resources\Users\Pages\CreateUser;
use App\Filament\Clusters\System\Resources\Users\Pages\EditUser;
use App\Filament\Clusters\System\Resources\Users\Pages\ListUsers;
use App\Filament\Clusters\System\Resources\Users\Pages\ViewUser;
use App\Filament\Clusters\System\Resources\Users\RelationManagers\InstructedLessonsRelationManager;
use App\Filament\Clusters\System\Resources\Users\RelationManagers\TherapistReservationsRelationManager;
use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification as ResetPassword;
use Filament\Facades\Filament;
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

    public function test_deactivated_staff_lose_panel_access(): void
    {
        $panel = Filament::getPanel('admin');

        $therapist = User::factory()->therapist()->create();
        $this->assertTrue($therapist->canAccessPanel($panel));

        // Former staff are kept as deactivated accounts so their historical
        // notes stay attributed; they must not be able to get back in.
        $therapist->update(['deactivated_at' => now()]);
        $this->assertFalse($therapist->fresh()->canAccessPanel($panel));

        $admin = User::factory()->admin()->create(['deactivated_at' => now()]);
        $this->assertFalse($admin->canAccessPanel($panel));
    }

    public function test_therapist_without_permission_cannot_view_users_list(): void
    {
        $therapist = User::factory()->therapist()->create();

        $this->actingAs($therapist)->get('/admin/system/users')->assertForbidden();
    }

    public function test_admin_can_view_users_list(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/system/users')->assertSuccessful();
    }

    public function test_users_list_excludes_customers(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $therapist = User::factory()->therapist()->create();
        $customer = User::factory()->customer()->create();

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$therapist])
            ->assertCanNotSeeTableRecords([$customer]);
    }

    public function test_customer_cannot_be_opened_via_users_resource(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $customer = User::factory()->customer()->create();

        $this->get("/admin/system/users/{$customer->getKey()}/edit")->assertNotFound();
    }

    public function test_user_can_be_created_with_account_type_and_direct_permission(): void
    {
        $permission = Permission::findOrCreate('View:User');

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Nový Uživatel',
                'email' => 'novy@example.test',
                'role' => UserRole::Therapist->value,
                'permissions' => [$permission->getKey()],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::where('email', 'novy@example.test')->first();

        $this->assertNotNull($created);
        $this->assertTrue($created->hasRole('therapist'));          // synced from Role
        $this->assertTrue($created->hasPermissionTo('View:User'));  // direct permission
        $this->assertNotNull($created->password);                   // random password generated on create
    }

    public function test_user_can_be_created_with_academic_titles(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Petra Novotná',
                'title_before' => 'Bc.',
                'title_after' => 'DiS.',
                'email' => 'petra.tituly@example.test',
                'role' => UserRole::Therapist->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::where('email', 'petra.tituly@example.test')->first();

        $this->assertNotNull($created);
        $this->assertSame('Bc.', $created->title_before);
        $this->assertSame('DiS.', $created->title_after);
        $this->assertSame('Bc. Petra Novotná, DiS.', $created->full_name);
    }

    public function test_create_user_validates_required_fields(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => null,
                'email' => 'not-an-email',
            ])
            ->call('create')
            ->assertHasFormErrors([
                'name' => 'required',
                'email' => 'email',
            ]);
    }

    public function test_admin_can_view_user_detail(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $therapist = User::factory()->therapist()->create();

        $this->get("/admin/system/users/{$therapist->getKey()}")->assertSuccessful();
    }

    public function test_therapist_relation_managers_are_visible_only_for_therapists(): void
    {
        $therapist = User::factory()->therapist()->create();
        $admin = User::factory()->admin()->create();

        $this->assertTrue(TherapistReservationsRelationManager::canViewForRecord($therapist, ViewUser::class));
        $this->assertTrue(InstructedLessonsRelationManager::canViewForRecord($therapist, ViewUser::class));
        $this->assertFalse(TherapistReservationsRelationManager::canViewForRecord($admin, ViewUser::class));
        $this->assertFalse(InstructedLessonsRelationManager::canViewForRecord($admin, ViewUser::class));
    }

    public function test_edit_page_can_send_password_reset_email(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $target = User::factory()->therapist()->create();

        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $target->getKey()])
            ->assertActionExists('impersonate')
            ->callAction('resetPassword', ['method' => 'email']);

        Notification::assertSentTo($target, ResetPassword::class);
    }

    public function test_edit_page_can_set_password_manually(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->therapist()->create();

        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $target->getKey()])
            ->callAction('resetPassword', [
                'method' => 'manual',
                'password' => 'nove-heslo-123',
                'password_confirmation' => 'nove-heslo-123',
            ]);

        $this->assertTrue(Hash::check('nove-heslo-123', $target->fresh()->password));
    }

    public function test_set_password_manually_requires_matching_confirmation(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->therapist()->create();

        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $target->getKey()])
            ->callAction('resetPassword', [
                'method' => 'manual',
                'password' => 'nove-heslo-123',
                'password_confirmation' => 'jine-heslo',
            ])
            ->assertHasActionErrors(['password']);
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
