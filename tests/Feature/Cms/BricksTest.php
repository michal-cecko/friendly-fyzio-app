<?php

namespace Tests\Feature\Cms;

use App\Enums\ServiceType;
use App\Enums\ServiceVisibility;
use App\Mason\BrickRegistry;
use App\Models\Page;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\TherapistProfile;
use App\Models\TherapistSpecialization;
use App\Models\User;
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

        $therapist = User::factory()->therapist()->create(['name' => 'Klára Specialistová']);
        TherapistProfile::factory()->for($therapist)->published()->create(['title' => 'Fyzioterapeutka']);

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
                $brick('team', ['title' => 'Náš tým']),
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
            ->assertSee('Klára Specialistová')
            ->assertSee('Přihlaste se');
    }

    public function test_team_brick_lists_only_published_profiles_and_links_them(): void
    {
        $brick = fn (string $id, array $config = []): array => [
            'type' => 'masonBrick',
            'attrs' => ['id' => $id, 'config' => $config],
        ];

        $publishedUser = User::factory()->therapist()->create(['name' => 'Mgr. Lucie Fičkerová']);
        $published = TherapistProfile::factory()->for($publishedUser)->published()->create([
            'slug' => 'lucie-fickerova',
            'title' => 'Fyzioterapeutka, zakladatelka',
        ]);
        TherapistSpecialization::factory()->create([
            'therapist_id' => $published->getKey(),
            'name' => 'Pánevní dno',
            'display_order' => 0,
        ]);

        $draftUser = User::factory()->therapist()->create(['name' => 'Jana Beránková']);
        $draft = TherapistProfile::factory()->for($draftUser)->unpublished()->create(['title' => 'Masérka']);

        Page::factory()->system('home')->create([
            'slug' => '/',
            'content' => [
                $brick('team', ['eyebrow' => 'Náš tým', 'title' => 'Seznamte se s naším týmem']),
            ],
        ]);

        $html = $this->get('/')
            ->assertOk()
            ->assertSee('Seznamte se s naším týmem')
            ->assertSee('Mgr. Lucie Fičkerová')
            ->assertSee('Fyzioterapeutka, zakladatelka')
            ->assertSee('Pánevní dno')
            // A therapist without a published profile stays off the public team
            // page (they remain bookable through the reservation wizard).
            ->assertDontSee('Jana Beránková')
            // Published profiles are clickable.
            ->assertSee('Shlédnout profil')
            ->getContent();

        $this->assertStringContainsString('href="'.$published->permalink.'"', $html);
        $this->assertStringNotContainsString('href="'.$draft->permalink.'"', $html);
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

    public function test_text_list_brick_renders_prose_and_highlighted_list_card(): void
    {
        $brick = fn (string $id, array $config = []): array => [
            'type' => 'masonBrick',
            'attrs' => ['id' => $id, 'config' => $config],
        ];

        Page::factory()->system('home')->create([
            'slug' => '/',
            'content' => [
                $brick('text-list', [
                    'eyebrow' => 'Jak to funguje',
                    'title' => 'Lymfatický systém',
                    'body' => '<p>Popis techniky.</p>',
                    'card_style' => 'warning',
                    'card_icon' => 'triangle-alert',
                    'card_title' => 'Kontraindikace',
                    'card_note' => 'Napářka není vhodná v těchto případech.',
                    'items' => [
                        ['text' => 'Infekční a horečnaté stavy'],
                        ['text' => 'Rizikové těhotenství'],
                    ],
                ]),
            ],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Jak to funguje')
            ->assertSee('Lymfatický systém')
            ->assertSee('Popis techniky.')
            ->assertSee('Kontraindikace')
            ->assertSee('Napářka není vhodná v těchto případech.')
            ->assertSee('Infekční a horečnaté stavy')
            ->assertSee('Rizikové těhotenství');
    }

    public function test_pricing_brick_renders_footer_note(): void
    {
        Page::factory()->system('home')->create([
            'slug' => '/',
            'content' => [[
                'type' => 'masonBrick',
                'attrs' => ['id' => 'pricing', 'config' => [
                    'title' => 'Ceník',
                    'rows' => [['name' => 'Napářka', 'note' => 'cca 60 minut', 'price' => '1 200 Kč']],
                    'note' => 'V ceně jsou bylinky a veškeré vybavení k proceduře.',
                ]],
            ]],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Napářka')
            ->assertSee('V ceně jsou bylinky a veškeré vybavení k proceduře.');
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

    public function test_unified_button_renders_style_color_and_link(): void
    {
        $brick = fn (string $id, array $config = []): array => [
            'type' => 'masonBrick',
            'attrs' => ['id' => $id, 'config' => $config],
        ];

        Page::factory()->system('home')->create([
            'slug' => '/',
            'content' => [
                $brick('cards', ['title' => 'Karty', 'cards' => [
                    [
                        'title' => 'Jóga',
                        'text' => 'Rezervovat',
                        'style' => 'primary',
                        'color' => '#ff0000',
                        'icon' => 'calendar',
                        'link_type' => 'custom',
                        'url' => '/rezervace',
                    ],
                ]]),
            ],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Rezervovat')
            ->assertSee('href="/rezervace"', false)
            // Pill wrapper for a non-text style.
            ->assertSee('rounded-full', false)
            // Custom color is applied inline on a solid style.
            ->assertSee('background-color: #ff0000', false);
    }

    public function test_bricks_still_render_legacy_button_fields(): void
    {
        $brick = fn (string $id, array $config = []): array => [
            'type' => 'masonBrick',
            'attrs' => ['id' => $id, 'config' => $config],
        ];

        Page::factory()->system('home')->create([
            'slug' => '/',
            'content' => [
                // Legacy: card uses link_text, feature card uses a heroicon-* icon.
                $brick('cards', ['title' => 'Karty', 'cards' => [
                    ['title' => 'Jóga', 'link_text' => 'Zjistit více', 'url' => '/joga'],
                ]]),
                $brick('feature-cards', ['title' => 'Služby', 'cards' => [
                    ['icon' => 'heroicon-o-heart', 'title' => 'Fyzioterapie', 'url' => '/fyzio'],
                ]]),
                // Legacy: last-minute uses button_text/button_url.
                $brick('last-minute', ['title' => 'Termíny', 'button_text' => 'Celý kalendář', 'button_url' => '/kalendar']),
            ],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Zjistit více')
            ->assertSee('href="/joga"', false)
            ->assertSee('Fyzioterapie')
            ->assertSee('Celý kalendář')
            ->assertSee('href="/kalendar"', false);
    }

    public function test_newsletter_brick_renders_the_livewire_subscribe_form(): void
    {
        Page::factory()->system('home')->create([
            'slug' => '/',
            'content' => [[
                'type' => 'masonBrick',
                'attrs' => ['id' => 'newsletter', 'config' => ['title' => 'Newsletter', 'button_text' => 'Odebírat']],
            ]],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('wire:submit="subscribe"', false)
            ->assertSee('Odebírat');
    }

    public function test_page_intro_brick_renders_title_and_subtitle(): void
    {
        Page::factory()->system('home')->create([
            'slug' => '/',
            'content' => [[
                'type' => 'masonBrick',
                'attrs' => ['id' => 'page-intro', 'config' => [
                    'title' => 'Ceník služeb',
                    'subtitle' => 'Přehled cen našich služeb.',
                ]],
            ]],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Ceník služeb')
            ->assertSee('Přehled cen našich služeb.');
    }

    public function test_price_list_brick_renders_tabs_with_service_and_manual_rows(): void
    {
        $category = ServiceCategory::factory()->create(['name' => 'Fyzioterapie', 'type' => ServiceType::Physiotherapy]);
        // A non-public service is still shown when explicitly referenced by a row.
        $service = Service::factory()->for($category, 'category')->create([
            'name' => 'Vstupní vyšetření',
            'duration_minutes' => 90,
            'price' => 1750,
            'visibility' => ServiceVisibility::Hidden,
            'published_at' => null,
        ]);

        Page::factory()->system('home')->create([
            'slug' => '/',
            'content' => [[
                'type' => 'masonBrick',
                'attrs' => ['id' => 'price-list', 'config' => [
                    'title' => 'Ceník',
                    'categories' => [
                        [
                            'label' => 'Fyzioterapie a kurzy',
                            // Heading left blank falls back to the tab label.
                            'rows' => [
                                ['service_id' => $service->id],
                            ],
                        ],
                        [
                            'label' => 'Ostatní',
                            'heading' => 'Ostatní služby a poplatky',
                            'rows' => [
                                ['name' => 'Storno poplatek', 'note' => '', 'price' => '100 % ceny'],
                            ],
                        ],
                    ],
                    'note' => '<p>Ceny jsou orientační.</p>',
                ]],
            ]],
        ]);

        $this->get('/')
            ->assertOk()
            // Tab labels; the second category proves multiple tabs render.
            ->assertSee('Fyzioterapie a kurzy')
            ->assertSee('Ostatní služby a poplatky')
            // Service-linked row pulls live name, duration and formatted price.
            ->assertSee('Vstupní vyšetření')
            ->assertSee('90 min')
            ->assertSee('1 750 Kč')
            // Manual free-text row (non-reservable fee).
            ->assertSee('Storno poplatek')
            ->assertSee('100 % ceny')
            ->assertSee('Ceny jsou orientační.');
    }
}
