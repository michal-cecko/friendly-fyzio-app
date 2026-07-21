<?php

namespace Tests\Feature;

use App\Models\EventCategory;
use App\Models\OneOffEvent;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventCategoryPageTest extends TestCase
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

    public function test_default_category_page_renders_with_the_archive(): void
    {
        $category = EventCategory::query()->where('slug', 'workshopy')->firstOrFail();
        $event = OneOffEvent::factory()->forCategory($category)->published()->create([
            'event_date' => today()->addWeeks(2)->toDateString(),
        ]);

        $this->get('/workshopy')
            ->assertOk()
            ->assertSee('Workshopy')
            ->assertSee('Aktuální nabídka')
            ->assertSee($event->name);
    }

    public function test_published_custom_page_overrides_the_default_layout(): void
    {
        $category = EventCategory::query()->where('slug', 'workshopy')->firstOrFail();
        Page::factory()->for($category, 'pageable')->create([
            'slug' => 'workshopy-vlastni-stranka',
            'content' => [$this->brick('hero', ['title' => 'Vlastní stránka workshopů'])],
        ]);

        $this->get('/workshopy')
            ->assertOk()
            ->assertSee('Vlastní stránka workshopů')
            ->assertDontSee('Aktuální nabídka');
    }

    public function test_draft_custom_page_falls_back_to_default_for_guests(): void
    {
        $category = EventCategory::query()->where('slug', 'workshopy')->firstOrFail();
        Page::factory()->draft()->for($category, 'pageable')->create([
            'slug' => 'workshopy-vlastni-stranka',
            'content' => [$this->brick('hero', ['title' => 'Vlastní stránka workshopů'])],
        ]);

        $this->get('/workshopy')
            ->assertOk()
            ->assertDontSee('Vlastní stránka workshopů')
            ->assertSee('Aktuální nabídka');
    }

    public function test_unpublished_category_is_404_for_guests_but_previewable_by_staff(): void
    {
        EventCategory::factory()->unpublished()->create([
            'name' => 'Chystaná kategorie',
            'slug' => 'chystana-kategorie',
        ]);

        $this->get('/chystana-kategorie')->assertNotFound();

        $this->actingAs(User::factory()->admin()->create());

        $this->get('/chystana-kategorie')
            ->assertOk()
            ->assertSee('Chystaná kategorie')
            ->assertSee('Náhled');
    }

    public function test_category_wins_over_a_page_sharing_its_slug_without_a_redirect_loop(): void
    {
        // The seeded /workshopy Page row shares the category slug; the category
        // must resolve first so the URL renders instead of 301-ing to itself.
        $category = EventCategory::query()->where('slug', 'workshopy')->firstOrFail();
        Page::factory()->for($category, 'pageable')->create([
            'slug' => 'workshopy',
            'content' => [$this->brick('hero', ['title' => 'Vlastní stránka workshopů'])],
        ]);

        $this->get('/workshopy')
            ->assertOk()
            ->assertSee('Vlastní stránka workshopů');
    }

    public function test_attached_page_own_slug_redirects_to_the_category_url(): void
    {
        $category = EventCategory::query()->where('slug', 'workshopy')->firstOrFail();
        Page::factory()->for($category, 'pageable')->create([
            'slug' => 'workshopy-vlastni-stranka',
            'content' => [$this->brick('hero', ['title' => 'Vlastní stránka workshopů'])],
        ]);

        $this->get('/workshopy-vlastni-stranka')
            ->assertRedirect(url('/workshopy'));
    }
}
