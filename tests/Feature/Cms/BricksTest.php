<?php

namespace Tests\Feature\Cms;

use App\Enums\ServiceType;
use App\Enums\ServiceVisibility;
use App\Mason\BrickRegistry;
use App\Models\Page;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BricksTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_registered_brick_is_well_formed(): void
    {
        foreach (BrickRegistry::flat() as $brick) {
            $this->assertNotEmpty($brick::getId(), "{$brick} has an empty id");
            $this->assertNotEmpty($brick::getLabel(), "{$brick} has an empty label");
            // Calling getIcon() validates the referenced Heroicon enum case exists.
            $brick::getIcon();
        }

        $this->addToAssertionCount(1);
    }

    public function test_homepage_renders_every_brick_type(): void
    {
        $brick = fn (string $id, array $config = []): array => [
            'type' => 'masonBrick',
            'attrs' => ['id' => $id, 'config' => $config],
        ];

        Page::factory()->system('home')->create([
            'slug' => '/',
            'content' => [
                $brick('hero', ['title' => 'Hero nadpis', 'eyebrow' => 'Vítejte', 'features' => '<ul><li>Bod jedna</li></ul>', 'buttons' => [['text' => 'Akce', 'url' => '/x', 'icon' => 'calendar', 'style' => 'primary']]]),
                $brick('section-heading', ['title' => 'Nadpis sekce']),
                $brick('rich-text', ['content' => '<p>Tělo textu.</p>']),
                $brick('last-minute', ['title' => 'Last-minute termíny', 'therapists' => [['name' => 'Jana', 'role' => 'Fyzioterapeutka', 'slots' => ['Dnes 14:00']]]]),
                $brick('category-cards', ['title' => 'Kurzy', 'categories' => [['icon' => 'activity', 'title' => 'Pohybové kurzy', 'items' => ['Jóga']]]]),
                $brick('feature-cards', ['title' => 'Naše služby', 'cards' => [
                    ['icon' => 'heroicon-o-heart', 'title' => 'Fyzioterapie', 'description' => 'Popis.'],
                ]]),
                $brick('cards', ['title' => 'Kurzy', 'cards' => [
                    ['title' => 'Jóga', 'meta' => 'leden 2026', 'description' => 'Popis.'],
                ]]),
                $brick('stats', ['stats' => [['value' => '2000+', 'label' => 'Klientů']]]),
                $brick('testimonials', ['title' => 'Reference', 'items' => [
                    ['quote' => 'Skvělé.', 'author' => 'Jana N.', 'role' => 'klientka'],
                ]]),
                $brick('cta-banner', ['title' => 'Přihlaste se', 'eyebrow' => 'Aktuálně']),
                $brick('instagram', ['title' => 'Sledujte nás', 'handle' => '@friendlyfyzio']),
                $brick('newsletter', ['title' => 'Newsletter']),
            ],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Hero nadpis')
            ->assertSee('Last-minute termíny')
            ->assertSee('Pohybové kurzy')
            ->assertSee('Naše služby')
            ->assertSee('Fyzioterapie')
            ->assertSee('2000+')
            ->assertSee('Reference')
            ->assertSee('Přihlaste se');
    }

    public function test_category_cards_brick_renders_clickable_rows_with_pulsing_dots(): void
    {
        $brick = fn (string $id, array $config = []): array => [
            'type' => 'masonBrick',
            'attrs' => ['id' => $id, 'config' => $config],
        ];

        Page::factory()->system('home')->create([
            'slug' => '/',
            'content' => [
                $brick('category-cards', ['title' => 'Právě přihlašujeme', 'categories' => [
                    ['icon' => 'activity', 'title' => 'Pohybové kurzy', 'url' => '/kurzy', 'items' => [
                        ['label' => 'Hormonální jóga', 'meta' => 'Začíná 12. 1. 2026', 'url' => '/kurzy/hormonalni-joga'],
                        ['label' => 'Jin jóga', 'meta' => 'Začíná 2. 2. 2026', 'url' => '/kurzy/jin-joga'],
                    ]],
                ]]),
            ],
        ]);

        $html = $this->get('/')->assertOk()
            ->assertSee('Hormonální jóga')
            ->assertSee('Začíná 12. 1. 2026')
            ->assertSee('href="/kurzy/hormonalni-joga"', false)
            ->assertSee('animate-ping', false)
            ->getContent();

        // Every row reacts on hover only — no row is pinned into the hover background.
        $this->assertStringContainsString('hover:bg-surface-alt', $html);
        $this->assertStringNotContainsString('text-sm text-neutral-700 bg-surface-alt', $html);
    }

    public function test_category_cards_brick_still_renders_plain_string_items(): void
    {
        $brick = fn (string $id, array $config = []): array => [
            'type' => 'masonBrick',
            'attrs' => ['id' => $id, 'config' => $config],
        ];

        Page::factory()->system('home')->create([
            'slug' => '/',
            'content' => [
                $brick('category-cards', ['title' => 'Kurzy', 'categories' => [
                    ['icon' => 'activity', 'title' => 'Pohybové kurzy', 'items' => ['Jóga']],
                ]]),
            ],
        ]);

        $this->get('/')->assertOk()
            ->assertSee('Pohybové kurzy')
            ->assertSee('Jóga');
    }

    public function test_steps_and_pricing_bricks_render(): void
    {
        $brick = fn (string $id, array $config = []): array => [
            'type' => 'masonBrick',
            'attrs' => ['id' => $id, 'config' => $config],
        ];

        $category = ServiceCategory::factory()->create();
        Service::factory()->create([
            'category_id' => $category->id,
            'name' => 'Konzultace',
            'visibility' => ServiceVisibility::Public,
            'published_at' => now(),
            'price' => 500,
            'duration_minutes' => 30,
        ]);

        Page::factory()->system('home')->create([
            'slug' => '/',
            'content' => [
                $brick('steps', ['title' => 'Jak to probíhá', 'steps' => [
                    ['title' => 'Objednání', 'description' => 'Vyberte si termín.'],
                    ['title' => 'Terapie', 'description' => 'Přijďte na sezení.'],
                ]]),
                $brick('pricing', ['title' => 'Ceník', 'category_id' => $category->id]),
                $brick('pricing', ['title' => 'Ceník ručně', 'rows' => [
                    ['name' => 'Balíček 5 vstupů', 'note' => 'platnost 6 měsíců', 'price' => '2 000 Kč'],
                ]]),
            ],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Jak to probíhá')
            ->assertSee('Objednání')
            ->assertSee('Terapie')
            ->assertSee('Ceník')
            ->assertSee('Konzultace')
            ->assertSee('500 Kč')
            ->assertSee('30 min')
            ->assertSee('Balíček 5 vstupů')
            ->assertSee('2 000 Kč');
    }

    public function test_service_cards_brick_renders_published_categories(): void
    {
        $brick = fn (string $id, array $config = []): array => [
            'type' => 'masonBrick',
            'attrs' => ['id' => $id, 'config' => $config],
        ];

        ServiceCategory::factory()->create(['name' => 'Fyzioterapie', 'slug' => 'fyzioterapie', 'type' => ServiceType::Physiotherapy]);
        ServiceCategory::factory()->create(['name' => 'Masáže', 'slug' => 'masaze', 'type' => ServiceType::Massage]);
        ServiceCategory::factory()->unpublished()->create(['name' => 'Skrytá kategorie', 'slug' => 'skryta']);

        Page::factory()->system('home')->create([
            'slug' => '/',
            'content' => [
                $brick('service-cards', ['title' => 'Naše nabídka', 'link_text' => 'Zjistit více']),
            ],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Naše nabídka')
            ->assertSee('Fyzioterapie')
            ->assertSee('Masáže')
            ->assertSee('sluzby/fyzioterapie', false)
            ->assertSee('sluzby/masaze', false)
            ->assertDontSee('Skrytá kategorie');
    }

    public function test_service_cards_brick_filters_by_type(): void
    {
        $brick = fn (string $id, array $config = []): array => [
            'type' => 'masonBrick',
            'attrs' => ['id' => $id, 'config' => $config],
        ];

        ServiceCategory::factory()->create(['name' => 'Fyzioterapie', 'slug' => 'fyzioterapie', 'type' => ServiceType::Physiotherapy]);
        ServiceCategory::factory()->create(['name' => 'Masáže', 'slug' => 'masaze', 'type' => ServiceType::Massage]);

        Page::factory()->system('home')->create([
            'slug' => '/',
            'content' => [
                $brick('service-cards', ['title' => 'Jen masáže', 'type' => ServiceType::Massage->value]),
            ],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Masáže')
            ->assertDontSee('Fyzioterapie');
    }

    public function test_rich_editor_accent_color_renders_and_unwraps_in_headings(): void
    {
        $brick = fn (string $id, array $config = []): array => [
            'type' => 'masonBrick',
            'attrs' => ['id' => $id, 'config' => $config],
        ];

        Page::factory()->system('home')->create([
            'slug' => '/',
            'content' => [
                $brick('section-heading', ['title' => '<p>Naše <span data-color="accent">nabídka</span></p>']),
            ],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('data-color="accent"', false)
            ->assertSee('nabídka', false)
            // The editor's wrapping <p> is unwrapped so the accent sits inline in the <h2>.
            ->assertDontSee('<p>Naše', false);
    }

    public function test_newsletter_submission_flashes_confirmation(): void
    {
        $this->post('/newsletter', ['email' => 'test@example.com'])
            ->assertRedirect();

        $this->assertTrue(session()->get('newsletter_success'));
    }
}
