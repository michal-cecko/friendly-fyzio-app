<?php

namespace Tests\Feature;

use App\Filament\Clusters\Provoz\Resources\Clients\ClientResource;
use App\Filament\Clusters\Provoz\Resources\Clients\Pages\ListClients;
use App\Filament\Clusters\Provoz\Resources\Users\Pages\ViewUser;
use App\Filament\Clusters\Provoz\Resources\Users\UserResource;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Who may look at, and who may change, each kind of account.
 *
 * Two Filament resources share the User model: Tým (staff) and Klienti
 * (customers). Everyone on staff reads both; only administrators write to a
 * staff account, while a customer is any staff member's to manage.
 */
class StaffUserAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        // Roles created by the factory carry no permissions of their own — only
        // the seeder wires them up, and every assertion here is permission-driven.
        // Through Artisan rather than $this->seed(): the seeder shells out to
        // shield:generate, which needs a real console output.
        $this->artisan('db:seed', ['--class' => RolePermissionSeeder::class])->assertSuccessful();
    }

    public function test_every_staff_member_reaches_the_team_resource(): void
    {
        foreach ([
            'therapist' => User::factory()->therapist()->create(),
            'lecturer' => User::factory()->lecturer()->create(),
            'admin' => User::factory()->admin()->create(),
        ] as $label => $user) {
            $this->actingAs($user);

            $this->assertTrue(UserResource::canAccess(), "A {$label} cannot open the team resource.");
        }
    }

    public function test_the_team_resource_is_read_only_below_admin(): void
    {
        $colleague = User::factory()->therapist()->create();

        foreach ([
            'therapist' => User::factory()->therapist()->create(),
            'lecturer' => User::factory()->lecturer()->create(),
        ] as $label => $user) {
            $this->actingAs($user);

            $this->assertFalse(UserResource::canManageStaff(), "A {$label} may manage staff.");
            $this->assertFalse(UserResource::canCreate(), "A {$label} may create staff accounts.");
            $this->assertFalse(UserResource::canEdit($colleague), "A {$label} may edit a colleague.");
            $this->assertFalse(UserResource::canDelete($colleague), "A {$label} may delete a colleague.");
            $this->assertFalse(UserResource::canDeleteAny(), "A {$label} may bulk-delete colleagues.");

            // …but reading a colleague's record is exactly the point.
            $this->assertTrue(UserResource::canView($colleague), "A {$label} cannot view a colleague.");
        }

        $this->actingAs(User::factory()->admin()->create());

        $this->assertTrue(UserResource::canManageStaff());
        $this->assertTrue(UserResource::canCreate());
        $this->assertTrue(UserResource::canEdit($colleague));
        $this->assertTrue(UserResource::canDelete($colleague));
    }

    public function test_a_therapist_may_open_a_colleagues_detail_page_but_not_its_edit_page(): void
    {
        $therapist = User::factory()->therapist()->create();
        $colleague = User::factory()->lecturer()->create();

        $this->actingAs($therapist);

        $this->get(UserResource::getUrl('index'))->assertSuccessful();
        $this->get(UserResource::getUrl('view', ['record' => $colleague]))->assertSuccessful();
        $this->get(UserResource::getUrl('edit', ['record' => $colleague]))->assertForbidden();
        $this->get(UserResource::getUrl('create'))->assertForbidden();
    }

    public function test_the_team_detail_page_hides_every_write_action_from_non_admin_staff(): void
    {
        $colleague = User::factory()->therapist()->create();

        $this->actingAs(User::factory()->therapist()->create());

        Livewire::test(ViewUser::class, ['record' => $colleague->getKey()])
            ->assertActionHidden('edit')
            ->assertActionHidden('resetPassword')
            ->assertActionHidden('deactivate')
            ->assertActionHidden('delete');

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ViewUser::class, ['record' => $colleague->getKey()])
            ->assertActionVisible('edit')
            ->assertActionVisible('resetPassword')
            ->assertActionVisible('deactivate');
    }

    public function test_staff_see_every_customer_not_only_the_ones_they_treated(): void
    {
        $therapist = User::factory()->therapist()->create();
        $lecturer = User::factory()->lecturer()->create();

        $customers = User::factory()->customer()->count(3)->create();

        foreach ([$therapist, $lecturer] as $user) {
            $this->actingAs($user);

            $ids = ClientResource::getEloquentQuery()->pluck('id');

            foreach ($customers as $customer) {
                $this->assertTrue($ids->contains($customer->id));
            }
        }
    }

    public function test_staff_may_create_edit_and_delete_customers(): void
    {
        $customer = User::factory()->customer()->create();

        foreach ([
            'therapist' => User::factory()->therapist()->create(),
            'lecturer' => User::factory()->lecturer()->create(),
        ] as $label => $user) {
            $this->actingAs($user);

            $this->assertTrue(ClientResource::canCreate(), "A {$label} cannot create a customer.");
            $this->assertTrue(ClientResource::canEdit($customer), "A {$label} cannot edit a customer.");
            $this->assertTrue(ClientResource::canDelete($customer), "A {$label} cannot delete a customer.");
        }
    }

    /**
     * An account can be both a customer and a colleague. It shows up in Klienti,
     * but changing it is still staff-account business — admins only.
     */
    public function test_a_customer_who_is_also_staff_stays_read_only_for_non_admins(): void
    {
        $staffCustomer = User::factory()->lecturer()->customer()->create();

        $this->actingAs(User::factory()->therapist()->create());

        $this->assertTrue(ClientResource::getEloquentQuery()->pluck('id')->contains($staffCustomer->id));
        $this->assertFalse(ClientResource::canEdit($staffCustomer));
        $this->assertFalse(ClientResource::canDelete($staffCustomer));

        $this->actingAs(User::factory()->admin()->create());

        $this->assertTrue(ClientResource::canEdit($staffCustomer));
        $this->assertTrue(ClientResource::canDelete($staffCustomer));
    }

    public function test_who_may_manage_which_account(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $admin = User::factory()->admin()->create();
        $therapist = User::factory()->therapist()->create();
        $customer = User::factory()->customer()->create();

        // Customers: open to anyone on staff, closed to a customer.
        $this->assertTrue($customer->isManageableBy($therapist));
        $this->assertTrue($customer->isManageableBy($admin));
        $this->assertFalse($customer->isManageableBy(User::factory()->customer()->create()));
        $this->assertFalse($customer->isManageableBy(null));

        // Colleagues: admins only, and a peer admin needs a super-admin.
        $this->assertFalse($therapist->isManageableBy(User::factory()->lecturer()->create()));
        $this->assertTrue($therapist->isManageableBy($admin));
        $this->assertFalse($admin->isManageableBy(User::factory()->admin()->create()));
        $this->assertTrue($admin->isManageableBy($superAdmin));
    }

    public function test_a_therapist_may_deactivate_a_customer_but_not_a_colleague(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs(User::factory()->therapist()->create());

        Livewire::test(ListClients::class)
            ->assertActionVisible(TestAction::make('deactivate')->table($customer));

        $colleague = User::factory()->therapist()->create();

        Livewire::test(ViewUser::class, ['record' => $colleague->getKey()])
            ->assertActionHidden('deactivate');
    }

    public function test_a_deactivated_customer_may_be_reactivated_by_a_therapist(): void
    {
        $customer = User::factory()->customer()->create(['deactivated_at' => now()]);

        $this->actingAs(User::factory()->therapist()->create());

        Livewire::test(ListClients::class)
            ->assertActionVisible(TestAction::make('reactivate')->table($customer))
            ->callAction(TestAction::make('reactivate')->table($customer));

        $this->assertNull($customer->refresh()->deactivated_at);
    }
}
