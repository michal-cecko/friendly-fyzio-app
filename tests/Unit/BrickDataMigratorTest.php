<?php

namespace Tests\Unit;

use App\Support\BrickDataMigrator;
use PHPUnit\Framework\TestCase;

class BrickDataMigratorTest extends TestCase
{
    /**
     * @param  array<int, mixed>  $config
     * @return array<int, mixed>
     */
    private function content(string $brickId, array $config): array
    {
        return [
            ['type' => 'masonBrick', 'attrs' => ['id' => $brickId, 'config' => $config]],
        ];
    }

    public function test_it_folds_legacy_button_fields_into_the_unified_shape(): void
    {
        $migrated = BrickDataMigrator::migrateContent(
            $this->content('last-minute', [
                'title' => 'Termíny',
                'button_text' => 'Zobrazit kalendář',
                'button_url' => '/kalendar',
            ]),
        );

        $config = $migrated[0]['attrs']['config'];

        $this->assertSame('Zobrazit kalendář', $config['text']);
        $this->assertSame('/kalendar', $config['url']);
        $this->assertSame('custom', $config['link_type']);
        $this->assertArrayNotHasKey('button_text', $config);
        $this->assertArrayNotHasKey('button_url', $config);
    }

    public function test_it_renames_card_link_text(): void
    {
        $migrated = BrickDataMigrator::migrateContent(
            $this->content('cards', [
                'cards' => [
                    ['title' => 'Jóga', 'link_text' => 'Více', 'url' => '/joga'],
                ],
            ]),
        );

        $card = $migrated[0]['attrs']['config']['cards'][0];

        $this->assertSame('Více', $card['text']);
        $this->assertArrayNotHasKey('link_text', $card);
    }

    public function test_it_migrates_category_cards_inner_links_and_string_items(): void
    {
        $migrated = BrickDataMigrator::migrateContent(
            $this->content('category-cards', [
                'button_text' => 'Vše',
                'button_url' => '/vse',
                'categories' => [
                    [
                        'title' => 'Kurzy',
                        'url' => '/kurzy',
                        'items' => [
                            'Jóga',
                            ['label' => 'Jin jóga', 'url' => '/kurzy/jin-joga'],
                        ],
                    ],
                ],
            ]),
        );

        $config = $migrated[0]['attrs']['config'];

        $this->assertSame('Vše', $config['text']);
        $this->assertSame('custom', $config['categories'][0]['link_type']);
        $this->assertSame(['label' => 'Jóga'], $config['categories'][0]['items'][0]);
        $this->assertSame('custom', $config['categories'][0]['items'][1]['link_type']);
    }

    public function test_it_is_idempotent(): void
    {
        $original = $this->content('category-cards', [
            'button_text' => 'Vše',
            'button_url' => '/vse',
            'categories' => [
                ['title' => 'Kurzy', 'url' => '/kurzy', 'items' => ['Jóga', ['label' => 'Jin', 'url' => '/jin']]],
            ],
        ]);

        $once = BrickDataMigrator::migrateContent($original);
        $twice = BrickDataMigrator::migrateContent($once);

        $this->assertSame($once, $twice);
    }

    public function test_it_leaves_already_unified_bricks_untouched(): void
    {
        $original = $this->content('cards', [
            'cards' => [
                ['title' => 'Jóga', 'text' => 'Více', 'style' => 'primary', 'link_type' => 'page', 'page_id' => 'abc'],
            ],
        ]);

        $this->assertSame($original, BrickDataMigrator::migrateContent($original));
    }
}
