<?php

namespace Tests\Feature\Cms;

use App\Mason\BrickRegistry;
use App\Models\Page;
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

    public function test_newsletter_submission_flashes_confirmation(): void
    {
        $this->post('/newsletter', ['email' => 'test@example.com'])
            ->assertRedirect();

        $this->assertTrue(session()->get('newsletter_success'));
    }
}
