<?php

namespace Tests\Feature\Cms;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPageTest extends TestCase
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

    public function test_homepage_renders_its_bricks(): void
    {
        Page::factory()->system('home')->create([
            'slug' => '/',
            'title' => 'Domů',
            'content' => [
                $this->brick('hero', ['title' => 'Friendly Fyzio', 'eyebrow' => 'FriendlyFyzio', 'features' => '<ul><li>klinika</li></ul>']),
                $this->brick('rich-text', ['content' => '<p>Vítejte v naší klinice.</p>']),
            ],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Friendly Fyzio')
            ->assertSee('klinika')
            ->assertSee('Vítejte v naší klinice.', false);
    }

    public function test_published_page_renders_by_slug(): void
    {
        Page::factory()->create([
            'slug' => 'o-nas',
            'title' => 'O nás',
            'content' => [$this->brick('hero', ['title' => 'O nás'])],
        ]);

        $this->get('/o-nas')->assertOk()->assertSee('O nás');
    }

    public function test_unpublished_page_returns_404(): void
    {
        Page::factory()->draft()->create(['slug' => 'skryta']);

        $this->get('/skryta')->assertNotFound();
    }

    public function test_draft_page_is_previewable_by_staff(): void
    {
        Page::factory()->draft()->create([
            'slug' => 'koncept',
            'content' => [$this->brick('hero', ['title' => 'Koncept stránky'])],
        ]);

        // Guests cannot see an unpublished page.
        $this->get('/koncept')->assertNotFound();

        // Staff (admin/manager) get a preview with a notice.
        $this->actingAs(User::factory()->admin()->create())
            ->get('/koncept')
            ->assertOk()
            ->assertSee('Koncept stránky')
            ->assertSee('Náhled konceptu');
    }

    public function test_missing_page_returns_404(): void
    {
        $this->get('/neexistuje')->assertNotFound();
    }

    public function test_system_pages_cannot_be_deleted(): void
    {
        $page = Page::factory()->system('home')->create(['slug' => '/']);

        $page->delete();

        $this->assertDatabaseHas('pages', ['id' => $page->id, 'deleted_at' => null]);
    }
}
