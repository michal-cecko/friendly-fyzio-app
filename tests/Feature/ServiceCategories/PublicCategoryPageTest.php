<?php

namespace Tests\Feature\ServiceCategories;

use App\Contracts\HasPermalink;
use App\Contracts\HasPublicPage;
use App\Enums\ServiceVisibility;
use App\Models\Page;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCategoryPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function brick(string $id, array $config = []): array
    {
        return ['type' => 'masonBrick', 'attrs' => ['id' => $id, 'config' => $config]];
    }

    public function test_models_implement_public_page_contracts(): void
    {
        $this->assertInstanceOf(HasPublicPage::class, new ServiceCategory);
        $this->assertInstanceOf(HasPermalink::class, new Page);
    }

    public function test_category_permalink_points_to_sluzby_url(): void
    {
        $category = ServiceCategory::factory()->create(['slug' => 'fyzioterapie']);

        $this->assertSame(url('/sluzby/fyzioterapie'), $category->permalink);
    }

    public function test_unattached_page_permalink_uses_its_own_slug(): void
    {
        $page = Page::factory()->create(['slug' => 'o-nas']);

        $this->assertSame(url('/o-nas'), $page->permalink);
    }

    public function test_attached_page_permalink_derives_from_owner(): void
    {
        $category = ServiceCategory::factory()->create(['slug' => 'relaxace']);
        $page = Page::factory()->for($category, 'pageable')->create(['slug' => 'relaxace-custom']);

        $this->assertSame(url('/sluzby/relaxace'), $page->fresh()->permalink);
    }

    public function test_default_category_page_renders_with_public_services(): void
    {
        $category = ServiceCategory::factory()->create([
            'slug' => 'fyzioterapie',
            'name' => 'Fyzioterapie',
            'description' => 'Popis kategorie.',
        ]);
        Service::factory()->create([
            'category_id' => $category->id,
            'name' => 'Vstupní vyšetření',
            'visibility' => ServiceVisibility::Public,
            'published_at' => now(),
            'price' => 800,
            'duration_minutes' => 60,
        ]);

        $this->get('/sluzby/fyzioterapie')
            ->assertOk()
            ->assertSee('Fyzioterapie')
            ->assertSee('Popis kategorie.')
            ->assertSee('Vstupní vyšetření')
            ->assertSee('800 Kč')
            ->assertSee('60 min');
    }

    public function test_default_category_page_hides_non_public_services(): void
    {
        $category = ServiceCategory::factory()->create(['slug' => 'masaz']);
        Service::factory()->create(['category_id' => $category->id, 'name' => 'Veřejná masáž', 'visibility' => ServiceVisibility::Public, 'published_at' => now()]);
        Service::factory()->create(['category_id' => $category->id, 'name' => 'Skrytá masáž', 'visibility' => ServiceVisibility::Hidden, 'published_at' => now()]);
        Service::factory()->create(['category_id' => $category->id, 'name' => 'Nepublikovaná masáž', 'visibility' => ServiceVisibility::Public, 'published_at' => null]);

        $this->get('/sluzby/masaz')
            ->assertOk()
            ->assertSee('Veřejná masáž')
            ->assertDontSee('Skrytá masáž')
            ->assertDontSee('Nepublikovaná masáž');
    }

    public function test_unknown_category_returns_404(): void
    {
        $this->get('/sluzby/neexistuje')->assertNotFound();
    }

    public function test_unpublished_category_returns_404_for_guests(): void
    {
        ServiceCategory::factory()->unpublished()->create(['slug' => 'skryta']);

        $this->get('/sluzby/skryta')->assertNotFound();
    }

    public function test_unpublished_category_is_previewable_by_staff(): void
    {
        ServiceCategory::factory()->unpublished()->create(['slug' => 'skryta', 'name' => 'Skrytá kategorie']);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/sluzby/skryta')
            ->assertOk()
            ->assertSee('Skrytá kategorie')
            ->assertSee('Náhled');
    }

    public function test_published_custom_page_overrides_default(): void
    {
        $category = ServiceCategory::factory()->create(['slug' => 'relaxace', 'name' => 'Relaxace']);
        Page::factory()->for($category, 'pageable')->create([
            'slug' => 'relaxace-custom',
            'content' => [$this->brick('hero', ['title' => 'Vlastní relaxace'])],
        ]);

        $this->get('/sluzby/relaxace')
            ->assertOk()
            ->assertSee('Vlastní relaxace')
            ->assertDontSee('Naše služby');
    }

    public function test_draft_custom_page_falls_back_to_default_for_guests(): void
    {
        $category = ServiceCategory::factory()->create(['slug' => 'relaxace', 'name' => 'Relaxace']);
        Page::factory()->draft()->for($category, 'pageable')->create([
            'slug' => 'relaxace-custom',
            'content' => [$this->brick('hero', ['title' => 'Vlastní relaxace'])],
        ]);
        Service::factory()->create(['category_id' => $category->id, 'name' => 'Relaxační masáž', 'visibility' => ServiceVisibility::Public, 'published_at' => now()]);

        $this->get('/sluzby/relaxace')
            ->assertOk()
            ->assertDontSee('Vlastní relaxace')
            ->assertSee('Naše služby')
            ->assertSee('Relaxační masáž');
    }

    public function test_draft_custom_page_is_previewable_by_staff(): void
    {
        $category = ServiceCategory::factory()->create(['slug' => 'relaxace']);
        Page::factory()->draft()->for($category, 'pageable')->create([
            'slug' => 'relaxace-custom',
            'content' => [$this->brick('hero', ['title' => 'Vlastní relaxace'])],
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/sluzby/relaxace')
            ->assertOk()
            ->assertSee('Vlastní relaxace')
            ->assertSee('Náhled konceptu');
    }

    public function test_attached_page_own_slug_redirects_to_permalink(): void
    {
        $category = ServiceCategory::factory()->create(['slug' => 'relaxace']);
        Page::factory()->for($category, 'pageable')->create(['slug' => 'relaxace-custom']);

        $this->get('/relaxace-custom')
            ->assertStatus(301)
            ->assertRedirect(url('/sluzby/relaxace'));
    }
}
