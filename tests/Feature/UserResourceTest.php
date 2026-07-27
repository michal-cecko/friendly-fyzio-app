<?php

namespace Tests\Feature;

use App\Enums\Capability;
use App\Filament\Clusters\Provoz\Resources\Users\Pages\CreateUser;
use App\Filament\Clusters\Provoz\Resources\Users\Pages\EditUser;
use App\Filament\Clusters\Provoz\Resources\Users\Pages\ListUsers;
use App\Filament\Clusters\Provoz\Resources\Users\Pages\ViewUser;
use App\Filament\Clusters\Provoz\Resources\Users\RelationManagers\InstructedLessonsRelationManager;
use App\Filament\Clusters\Provoz\Resources\Users\RelationManagers\TherapistReservationsRelationManager;
use App\Filament\Clusters\Provoz\Resources\Users\UserResource;
use App\Filament\Pages\Calendar;
use App\Filament\Widgets\ReservationCalendar;
use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification as ResetPassword;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
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

    public function test_capabilities_map_to_the_backing_shield_roles(): void
    {
        $this->assertTrue(User::factory()->admin()->create()->hasRole('admin'));
        $this->assertTrue(User::factory()->therapist()->create()->hasRole('therapist'));
        $this->assertTrue(User::factory()->lecturer()->create()->hasRole('lecturer'));
        $this->assertFalse(
            User::factory()->customer()->create()->hasAnyRole(['super_admin', 'admin', 'therapist', 'lecturer'])
        );

        // Capabilities compose: an admin who also practises holds both.
        $both = User::factory()->admin()->therapist()->create();
        $this->assertTrue($both->isAdmin());
        $this->assertTrue($both->isTherapist());
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

    public function test_admin_can_deactivate_a_user_from_the_detail_page(): void
    {
        $panel = Filament::getPanel('admin');

        $this->actingAs(User::factory()->admin()->create());

        $therapist = User::factory()->therapist()->create();
        $this->assertTrue($therapist->canAccessPanel($panel));

        Livewire::test(ViewUser::class, ['record' => $therapist->getKey()])
            ->callAction('deactivate');

        $this->assertTrue($therapist->fresh()->isDeactivated());
        $this->assertNotNull($therapist->fresh()->deactivated_at);
        // End-to-end proof: the enforcement layer now locks them out.
        $this->assertFalse($therapist->fresh()->canAccessPanel($panel));
    }

    public function test_deactivate_action_is_hidden_on_your_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        Livewire::test(ViewUser::class, ['record' => $admin->getKey()])
            ->assertActionHidden('deactivate');
    }

    public function test_only_a_super_admin_may_deactivate_an_admin_account(): void
    {
        $plainAdmin = User::factory()->admin()->create();
        $superAdmin = User::factory()->create();
        $superAdmin->grantCapability(Capability::SuperAdmin);

        // A plain admin cannot deactivate a peer admin.
        $this->actingAs($plainAdmin);
        Livewire::test(ViewUser::class, ['record' => User::factory()->admin()->create()->getKey()])
            ->assertActionHidden('deactivate');

        // A super-admin can.
        $this->actingAs($superAdmin);
        Livewire::test(ViewUser::class, ['record' => User::factory()->admin()->create()->getKey()])
            ->assertActionVisible('deactivate');
    }

    public function test_reactivate_action_clears_the_deactivation(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $therapist = User::factory()->therapist()->create(['deactivated_at' => now()]);

        Livewire::test(ViewUser::class, ['record' => $therapist->getKey()])
            ->callAction('reactivate');

        $this->assertFalse($therapist->fresh()->isDeactivated());
        // The reactivation date is kept for the record.
        $this->assertNotNull($therapist->fresh()->reactivated_at);
    }

    public function test_a_deactivated_user_cannot_be_impersonated(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $active = User::factory()->therapist()->create();
        $deactivated = User::factory()->therapist()->create(['deactivated_at' => now()]);

        // Impersonating a deactivated account would sidestep its lockout; it must
        // be reactivated first.
        $this->assertTrue($active->canBeImpersonated());
        $this->assertFalse($deactivated->canBeImpersonated());

        Livewire::test(ViewUser::class, ['record' => $active->getKey()])
            ->assertActionVisible('impersonate');
        Livewire::test(ViewUser::class, ['record' => $deactivated->getKey()])
            ->assertActionHidden('impersonate');
    }

    public function test_edit_page_exposes_a_header_save_button(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $target = User::factory()->therapist()->create();

        // Save is reachable from the header as well as below the form; it
        // submits via the page's `save` method (covered by the create/edit
        // persistence tests).
        Livewire::test(EditUser::class, ['record' => $target->getKey()])
            ->assertActionExists('saveHeader');
    }

    public function test_therapist_without_permission_cannot_view_users_list(): void
    {
        $therapist = User::factory()->therapist()->create();

        $this->actingAs($therapist)->get('/admin/provoz/users')->assertForbidden();
    }

    public function test_admin_can_view_users_list(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/provoz/users')->assertSuccessful();
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

        $this->get("/admin/provoz/users/{$customer->getKey()}/edit")->assertNotFound();
    }

    public function test_user_can_be_created_with_account_type_and_direct_permission(): void
    {
        $permission = Permission::findOrCreate('View:User');

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Nový Uživatel',
                'email' => 'novy@example.test',
                'capabilities' => [Capability::Therapist->value],
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
                'capabilities' => [Capability::Therapist->value],
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

        $this->get("/admin/provoz/users/{$therapist->getKey()}")->assertSuccessful();
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

    public function test_therapist_reservations_link_to_the_calendar_filtered_to_that_therapist(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $therapist = User::factory()->therapist()->create();

        Livewire::test(TherapistReservationsRelationManager::class, [
            'ownerRecord' => $therapist,
            'pageClass' => ViewUser::class,
        ])
            ->assertActionExists(TestAction::make('viewInCalendar')->table())
            ->assertActionHasUrl(
                TestAction::make('viewInCalendar')->table(),
                Calendar::getUrl(['therapists' => [$therapist->staffProfile->getKey()]]),
            );
    }

    public function test_calendar_preselects_the_therapist_from_the_url_filter(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $profileId = User::factory()->therapist()->create()->staffProfile->getKey();

        Livewire::withQueryParams(['therapists' => [$profileId]])
            ->test(ReservationCalendar::class)
            ->assertSet('therapistIds', [$profileId]);
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

    public function test_impersonation_is_an_admin_capability(): void
    {
        $this->assertTrue(User::factory()->admin()->create()->canImpersonate());
        $this->assertTrue(User::factory()->admin()->therapist()->create()->canImpersonate());
        $this->assertFalse(User::factory()->therapist()->create()->canImpersonate());
        $this->assertFalse(User::factory()->lecturer()->create()->canImpersonate());
        $this->assertFalse(User::factory()->customer()->create()->canImpersonate());
    }

    public function test_only_a_super_admin_may_delete_an_admin_account(): void
    {
        $admin = User::factory()->admin()->create();
        $superAdmin = User::factory()->create();
        $superAdmin->grantCapability(Capability::SuperAdmin);
        $plainTherapist = User::factory()->therapist()->create();

        $this->actingAs($admin);
        $this->assertFalse(UserResource::canDeleteUser(User::factory()->admin()->create()));
        $this->assertTrue(UserResource::canDeleteUser($plainTherapist));

        $this->actingAs($superAdmin);
        $this->assertTrue(UserResource::canDeleteUser(User::factory()->admin()->create()));
    }

    public function test_row_actions_follow_the_records_trashed_state(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $active = User::factory()->therapist()->create();
        $trashed = User::factory()->therapist()->create();
        $trashed->delete();

        Livewire::test(ListUsers::class)
            // An overridden ->visible() replaces Filament's own trashed check, so
            // these three used to show on every row regardless of state.
            ->assertActionVisible(TestAction::make('delete')->table($active))
            ->assertActionHidden(TestAction::make('restore')->table($active))
            ->assertActionHidden(TestAction::make('forceDelete')->table($active));

        Livewire::test(ListUsers::class)
            ->filterTable('trashed', true)
            ->assertActionHidden(TestAction::make('delete')->table($trashed))
            ->assertActionVisible(TestAction::make('restore')->table($trashed))
            ->assertActionVisible(TestAction::make('forceDelete')->table($trashed));
    }

    public function test_the_users_list_has_no_deactivate_row_action(): void
    {
        // Deactivating cancels live bookings — it lives on the detail pages, not
        // one click away in the row. Reactivating stays.
        $this->actingAs(User::factory()->admin()->create());

        $deactivated = User::factory()->therapist()->create(['deactivated_at' => now()]);

        Livewire::test(ListUsers::class)
            ->assertActionDoesNotExist(TestAction::make('deactivate')->table($deactivated))
            ->assertActionVisible(TestAction::make('reactivate')->table($deactivated));
    }

    public function test_a_plain_admin_cannot_grant_admin_or_super_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->therapist()->create();

        // A tampered submission adding Admin/SuperAdmin is ignored for a non-super-admin.
        $target->applyCapabilitySelection(
            [Capability::Therapist, Capability::Admin, Capability::SuperAdmin],
            $admin,
        );

        $this->assertTrue($target->fresh()->isTherapist());
        $this->assertFalse($target->fresh()->isAdmin());
    }
}
