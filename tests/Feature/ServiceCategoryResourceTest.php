<?php

namespace Tests\Feature;

use App\Filament\Clusters\Provoz\Resources\ServiceCategories\Pages\ViewServiceCategory;
use App\Filament\Clusters\Provoz\Resources\ServiceCategories\RelationManagers\ServicesRelationManager;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
}
