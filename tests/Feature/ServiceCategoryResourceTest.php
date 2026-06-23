<?php

namespace Tests\Feature;

use App\Models\ServiceCategory;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
