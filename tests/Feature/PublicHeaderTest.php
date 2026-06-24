<?php

namespace Tests\Feature;

use App\Filament\Clusters\Obsah\Resources\Pages\PageResource;
use App\Filament\Clusters\Provoz\Resources\ServiceCategories\ServiceCategoryResource;
use App\Models\Page;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_see_administrace_account_link(): void
    {
        $page = Page::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get("/{$page->slug}")
            ->assertSuccessful()
            ->assertSee('Administrace')
            ->assertSee(url('/admin'))
            ->assertDontSee('Klientská zóna');
    }

    public function test_customers_see_client_zone_account_link(): void
    {
        $page = Page::factory()->create();

        $this->actingAs(User::factory()->customer()->create())
            ->get("/{$page->slug}")
            ->assertSuccessful()
            ->assertSee('Klientská zóna')
            ->assertSee(url('/klientska-zona'))
            ->assertDontSee('Administrace');
    }

    public function test_staff_see_edit_link_on_a_page(): void
    {
        $page = Page::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get("/{$page->slug}")
            ->assertSuccessful()
            ->assertSee('Upravit tuto stránku')
            ->assertSee(PageResource::getUrl('edit', ['record' => $page]));
    }

    public function test_staff_see_edit_link_on_a_service_category(): void
    {
        $category = ServiceCategory::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get("/sluzby/{$category->slug}")
            ->assertSuccessful()
            ->assertSee('Upravit tuto stránku')
            ->assertSee(ServiceCategoryResource::getUrl('edit', ['record' => $category]));
    }

    public function test_customers_do_not_see_edit_link(): void
    {
        $page = Page::factory()->create();

        $this->actingAs(User::factory()->customer()->create())
            ->get("/{$page->slug}")
            ->assertSuccessful()
            ->assertDontSee('Upravit tuto stránku');
    }

    public function test_guests_do_not_see_edit_link(): void
    {
        $page = Page::factory()->create();

        $this->get("/{$page->slug}")
            ->assertSuccessful()
            ->assertDontSee('Upravit tuto stránku');
    }
}
