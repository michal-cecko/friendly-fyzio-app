<?php

namespace Database\Seeders;

use App\Enums\PageStatus;
use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        $this->homepage();

        foreach ([
            'o-nas' => 'O nás',
            'sluzby' => 'Služby',
            'kurzy' => 'Kurzy',
            'kontakt' => 'Kontakt',
        ] as $slug => $title) {
            Page::updateOrCreate(
                ['system_key' => $slug],
                [
                    'slug' => $slug,
                    'title' => $title,
                    'is_system' => true,
                    'status' => PageStatus::Published,
                    'published_at' => now(),
                    'content' => [
                        $this->brick('hero', [
                            'badge' => 'Friendly Fyzio',
                            'title' => $title,
                            'subtitle' => 'Tuto stránku právě připravujeme. Brzy zde najdete více informací.',
                        ]),
                        $this->brick('rich-text', [
                            'content' => '<p>Obsah stránky připravujeme. Mezitím nás můžete kontaktovat nebo si prohlédnout naši nabídku.</p>',
                        ]),
                    ],
                ],
            );
        }
    }

    private function homepage(): void
    {
        Page::updateOrCreate(
            ['system_key' => 'home'],
            [
                'slug' => '/',
                'title' => 'Domů',
                'is_system' => true,
                'status' => PageStatus::Published,
                'published_at' => now(),
                'meta_title' => 'Friendly Fyzio – fyzioterapie a péče o ženské zdraví',
                'meta_description' => 'Komplexní fyzioterapie, přístrojová terapie a pohybové kurzy se specializací na ženské zdraví. Individuální přístup a zkušený tým.',
                'content' => [
                    $this->brick('hero', [
                        'badge' => 'Vaše cesta ke zdraví',
                        'title' => 'Fyzioterapie s důrazem na',
                        'title_accent' => 'ženské zdraví',
                        'subtitle' => 'Komplexní péče o váš pohybový systém. Individuální přístup, moderní metody a specializované kurzy vedené zkušenými fyzioterapeutkami.',
                        'cta_link_type' => 'custom',
                        'cta_url' => '/kontakt',
                        'cta_text' => 'Objednat se',
                        'secondary_cta_link_type' => 'custom',
                        'secondary_cta_url' => '/sluzby',
                        'secondary_cta_text' => 'Naše služby',
                    ]),
                    $this->brick('feature-cards', [
                        'eyebrow' => 'Proč my',
                        'title' => 'Proč si vybrat Friendly Fyzio',
                        'subtitle' => 'Spojujeme odbornost, individuální přístup a specializaci na ženské zdraví.',
                        'columns' => 3,
                        'cards' => [
                            ['icon' => 'heroicon-o-heart', 'title' => 'Individuální přístup', 'description' => 'Každému klientovi věnujeme čas a sestavíme terapeutický plán na míru.'],
                            ['icon' => 'heroicon-o-sparkles', 'title' => 'Specializace na ženské zdraví', 'description' => 'Těhotenství, poporodní péče i hormonální rovnováha.'],
                            ['icon' => 'heroicon-o-academic-cap', 'title' => 'Zkušený tým', 'description' => 'Certifikované fyzioterapeutky s dlouholetou praxí.'],
                        ],
                    ]),
                    $this->brick('cta-banner', [
                        'eyebrow' => 'Aktuálně přihlašujeme',
                        'title' => 'Přihlašování na období leden – duben 2026',
                        'subtitle' => 'Vyberte si z nabídky pohybových kurzů vedených našimi fyzioterapeutkami. Hormonální jóga, SM systém, kurzy pro těhotné a další.',
                        'cta_link_type' => 'custom',
                        'cta_url' => '/kurzy',
                        'cta_text' => 'Zobrazit kurzy',
                    ]),
                    $this->brick('cards', [
                        'eyebrow' => 'Aktuální nabídka',
                        'title' => 'Pohybové kurzy',
                        'subtitle' => 'Skupinové lekce vedené odbornicemi v příjemném prostředí.',
                        'cards' => [
                            ['title' => 'Hormonální jóga', 'meta' => 'Úterý 18:00', 'description' => 'Cvičení podporující hormonální rovnováhu.', 'link_type' => 'custom', 'url' => '/kurzy'],
                            ['title' => 'SM systém', 'meta' => 'Středa 17:00', 'description' => 'Stabilizace a mobilizace páteře.', 'link_type' => 'custom', 'url' => '/kurzy'],
                            ['title' => 'Kurz pro těhotné', 'meta' => 'Čtvrtek 16:00', 'description' => 'Příprava těla na porod.', 'link_type' => 'custom', 'url' => '/kurzy'],
                        ],
                    ]),
                    $this->brick('feature-cards', [
                        'eyebrow' => 'Naše nabídka',
                        'title' => 'Naše služby',
                        'subtitle' => 'Komplexní péče o váš pohybový systém s důrazem na individuální přístup.',
                        'columns' => 4,
                        'cards' => [
                            ['icon' => 'heroicon-o-hand-raised', 'title' => 'Fyzioterapie', 'description' => 'Manuální terapie a rehabilitace.', 'link_type' => 'custom', 'url' => '/sluzby'],
                            ['icon' => 'heroicon-o-bolt', 'title' => 'Přístrojová terapie', 'description' => 'Moderní přístrojové metody.', 'link_type' => 'custom', 'url' => '/sluzby'],
                            ['icon' => 'heroicon-o-academic-cap', 'title' => 'Kurzy', 'description' => 'Skupinové pohybové kurzy.', 'link_type' => 'custom', 'url' => '/kurzy'],
                            ['icon' => 'heroicon-o-sun', 'title' => 'Relaxace', 'description' => 'Masáže a relaxační techniky.', 'link_type' => 'custom', 'url' => '/sluzby'],
                        ],
                    ]),
                    $this->brick('testimonials', [
                        'eyebrow' => 'Reference',
                        'title' => 'Co říkají naši klienti',
                        'subtitle' => 'Pečujeme o každého klienta a jejich spokojenost je pro nás prioritou.',
                        'items' => [
                            ['quote' => 'Konečně cítím úlevu od bolesti zad. Přístup byl naprosto profesionální.', 'author' => 'Jana N.', 'role' => 'klientka'],
                            ['quote' => 'Kurz pro těhotné mi moc pomohl k pohodovému porodu. Děkuji!', 'author' => 'Petra K.', 'role' => 'klientka'],
                            ['quote' => 'Skvělý tým, který se opravdu zajímá o výsledek. Vřele doporučuji.', 'author' => 'Lucie M.', 'role' => 'klientka'],
                        ],
                    ]),
                    $this->brick('stats', [
                        'stats' => [
                            ['value' => '2000+', 'label' => 'Spokojených klientů'],
                            ['value' => '15+', 'label' => 'Let zkušeností'],
                            ['value' => '20+', 'label' => 'Pohybových kurzů'],
                            ['value' => '98%', 'label' => 'Spokojenost klientů'],
                        ],
                    ]),
                    $this->brick('instagram', [
                        'eyebrow' => 'Sledujte nás',
                        'title' => '@friendlyfyzio',
                        'subtitle' => 'Nahlédněte do našeho dění, sledujte tipy na cvičení a novinky z naší kliniky.',
                        'handle' => '@friendlyfyzio',
                        'cta_link_type' => 'custom',
                        'cta_url' => 'https://instagram.com',
                        'cta_text' => 'Sledovat na Instagramu',
                    ]),
                    $this->brick('newsletter', [
                        'title' => 'Zůstaňte v obraze',
                        'subtitle' => 'Přihlaste se k odběru novinek a tipů pro vaše zdraví.',
                        'button_text' => 'Odebírat',
                    ]),
                ],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function brick(string $id, array $config): array
    {
        return ['type' => 'masonBrick', 'attrs' => ['id' => $id, 'config' => $config]];
    }
}
