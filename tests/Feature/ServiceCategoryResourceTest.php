<?php

namespace Tests\Feature;

use App\Filament\Clusters\Provoz\Resources\ServiceCategories\Pages\ViewServiceCategory;
use App\Filament\Clusters\Provoz\Resources\ServiceCategories\RelationManagers\ServicesRelationManager;
use App\Filament\Clusters\Provoz\Resources\ServiceCategories\ServiceCategoryResource;
use App\Filament\Clusters\Provoz\Resources\Services\ServiceResource;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ServiceCategoryResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_admin_can_view_service_categories_list(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/provoz/service-categories')
            ->assertSuccessful();
    }

    public function test_admin_can_view_service_category_detail(): void
    {
        $category = ServiceCategory::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get("/admin/provoz/service-categories/{$category->getKey()}")
            ->assertSuccessful();
    }

    public function test_services_relation_manager_lists_only_the_category_services(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $category = ServiceCategory::factory()->create();
        $ownServices = Service::factory()->count(2)->create(['category_id' => $category->getKey()]);
        $otherService = Service::factory()->create();

        Livewire::test(ServicesRelationManager::class, [
            'ownerRecord' => $category,
            'pageClass' => ViewServiceCategory::class,
        ])
            ->assertCanSeeTableRecords($ownServices)
            ->assertCanNotSeeTableRecords([$otherService]);
    }

    public function test_admin_sees_the_create_service_action(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ServicesRelationManager::class, [
            'ownerRecord' => ServiceCategory::factory()->create(),
            'pageClass' => ViewServiceCategory::class,
        ])
            ->assertActionVisible(TestAction::make('createService')->table());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function readOnlyStaffProvider(): array
    {
        return [
            'therapist' => ['therapist'],
            'lecturer' => ['lecturer'],
        ];
    }

    #[DataProvider('readOnlyStaffProvider')]
    public function test_staff_without_the_create_service_permission_do_not_see_the_action(string $state): void
    {
        $this->seedRolesAndPermissions();

        $this->actingAs(User::factory()->{$state}()->create());

        Livewire::test(ServicesRelationManager::class, [
            'ownerRecord' => ServiceCategory::factory()->create(),
            'pageClass' => ViewServiceCategory::class,
        ])
            ->assertActionHidden(TestAction::make('createService')->table());
    }

    #[DataProvider('readOnlyStaffProvider')]
    public function test_service_categories_are_hidden_from_therapists_and_lecturers(string $state): void
    {
        $this->seedRolesAndPermissions();

        $category = ServiceCategory::factory()->create();

        $this->actingAs(User::factory()->{$state}()->create());

        $this->assertFalse(ServiceCategoryResource::canAccess());
        $this->assertFalse(ServiceCategoryResource::canView($category));

        $this->get('/admin/provoz/service-categories')->assertForbidden();
        $this->get("/admin/provoz/service-categories/{$category->getKey()}")->assertForbidden();
    }

    public function test_therapists_keep_access_to_the_service_catalogue_itself(): void
    {
        $this->seedRolesAndPermissions();

        $this->actingAs(User::factory()->therapist()->create());

        $this->assertTrue(ServiceResource::canAccess());
    }

    /**
     * Roles created by the factory carry no permissions of their own — only the
     * seeder wires them up, so any permission-driven assertion needs it first.
     */
    private function seedRolesAndPermissions(): void
    {
        // Through Artisan rather than $this->seed(): the seeder shells out to
        // shield:generate, which needs a real console output.
        $this->artisan('db:seed', ['--class' => RolePermissionSeeder::class])->assertSuccessful();
    }
}
