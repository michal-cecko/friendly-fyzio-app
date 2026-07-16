<?php

namespace Tests\Feature\Services;

use App\Contracts\HasPublicPage;
use App\Enums\ServiceVisibility;
use App\Models\Page;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicServicePageTest extends TestCase
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

    private function publicService(ServiceCategory $category, array $attributes = []): Service
    {
        return Service::factory()->create(array_merge([
            'category_id' => $category->id,
            'visibility' => ServiceVisibility::Public,
            'published_at' => now(),
        ], $attributes));
    }

    public function test_service_implements_public_page_contract(): void
    {
        $this->assertInstanceOf(HasPublicPage::class, new Service);
    }

    public function test_service_permalink_is_nested_under_category(): void
    {
        $category = ServiceCategory::factory()->create(['slug' => 'fyzioterapie']);
        $service = $this->publicService($category, ['slug' => 'terapie-panevniho-dna']);

        $this->assertSame(url('/sluzby/fyzioterapie/terapie-panevniho-dna'), $service->permalink);
    }

    public function test_default_service_page_renders(): void
    {
        $category = ServiceCategory::factory()->create(['slug' => 'fyzioterapie']);
        $this->publicService($category, [
            'slug' => 'vstupni-vysetreni',
            'name' => 'Vstupní vyšetření',
            'price' => 1300,
            'duration_minutes' => 90,
        ]);

        $this->get('/sluzby/fyzioterapie/vstupni-vysetreni')
            ->assertOk()
            ->assertSee('Vstupní vyšetření')
            ->assertSee('1 300 Kč')
            ->assertSee('90 min');
    }

    public function test_published_custom_page_overrides_default(): void
    {
        $category = ServiceCategory::factory()->create(['slug' => 'fyzioterapie']);
        $service = $this->publicService($category, [
            'slug' => 'terapie-panevniho-dna',
            'name' => 'Vstupní vyšetření',
            'duration_minutes' => 90,
        ]);
        Page::factory()->for($service, 'pageable')->create([
            'slug' => 'terapie-panevniho-dna-vlastni-stranka',
            'content' => [$this->brick('hero', ['title' => 'Terapie pánevního dna'])],
        ]);

        // The service name legitimately appears in the breadcrumb trail, so probe
        // for the default layout by its duration line instead of the name.
        $this->get('/sluzby/fyzioterapie/terapie-panevniho-dna')
            ->assertOk()
            ->assertSee('Terapie pánevního dna')
            ->assertDontSee('90 min');
    }

    public function test_hidden_service_with_published_custom_page_is_public(): void
    {
        // Topic pages are intentionally Hidden from booking/listings but must still
        // serve their published marketing page to the public.
        $category = ServiceCategory::factory()->create(['slug' => 'fyzioterapie']);
        $service = Service::factory()->create([
            'category_id' => $category->id,
            'slug' => 'terapie-panevniho-dna',
            'visibility' => ServiceVisibility::Hidden,
            'published_at' => now(),
        ]);
        Page::factory()->for($service, 'pageable')->create([
            'slug' => 'terapie-panevniho-dna-vlastni-stranka',
            'content' => [$this->brick('hero', ['title' => 'Terapie pánevního dna'])],
        ]);

        $this->get('/sluzby/fyzioterapie/terapie-panevniho-dna')
            ->assertOk()
            ->assertSee('Terapie pánevního dna')
            ->assertDontSee('Náhled');
    }

    public function test_hidden_service_without_custom_page_returns_404_for_guests(): void
    {
        $category = ServiceCategory::factory()->create(['slug' => 'fyzioterapie']);
        Service::factory()->create([
            'category_id' => $category->id,
            'slug' => 'skryta-sluzba',
            'visibility' => ServiceVisibility::Hidden,
            'published_at' => now(),
        ]);

        $this->get('/sluzby/fyzioterapie/skryta-sluzba')->assertNotFound();
    }

    public function test_hidden_service_default_page_previewable_by_staff(): void
    {
        $category = ServiceCategory::factory()->create(['slug' => 'fyzioterapie']);
        Service::factory()->create([
            'category_id' => $category->id,
            'slug' => 'skryta-sluzba',
            'name' => 'Skrytá služba',
            'visibility' => ServiceVisibility::Hidden,
            'published_at' => now(),
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/sluzby/fyzioterapie/skryta-sluzba')
            ->assertOk()
            ->assertSee('Skrytá služba')
            ->assertSee('Náhled');
    }

    public function test_draft_custom_page_falls_back_to_default_for_guests(): void
    {
        $category = ServiceCategory::factory()->create(['slug' => 'fyzioterapie']);
        $service = $this->publicService($category, ['slug' => 'vstupni-vysetreni', 'name' => 'Vstupní vyšetření']);
        Page::factory()->draft()->for($service, 'pageable')->create([
            'slug' => 'vstupni-vysetreni-vlastni-stranka',
            'content' => [$this->brick('hero', ['title' => 'Vlastní stránka'])],
        ]);

        $this->get('/sluzby/fyzioterapie/vstupni-vysetreni')
            ->assertOk()
            ->assertDontSee('Vlastní stránka')
            ->assertSee('Vstupní vyšetření');
    }

    public function test_service_not_in_category_returns_404(): void
    {
        $categoryA = ServiceCategory::factory()->create(['slug' => 'fyzioterapie']);
        $categoryB = ServiceCategory::factory()->create(['slug' => 'relaxace']);
        $this->publicService($categoryA, ['slug' => 'sluzba-a']);

        $this->get('/sluzby/relaxace/sluzba-a')->assertNotFound();
    }

    public function test_unknown_service_returns_404(): void
    {
        ServiceCategory::factory()->create(['slug' => 'fyzioterapie']);

        $this->get('/sluzby/fyzioterapie/neexistuje')->assertNotFound();
    }
}
