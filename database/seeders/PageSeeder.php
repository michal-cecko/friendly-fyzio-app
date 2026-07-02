<?php

namespace Database\Seeders;

use App\Models\Page;
use Database\Seeders\Concerns\ImportsMedia;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    use ImportsMedia;

    public function run(): void
    {
        $this->homepage();
        $this->aboutPage();

        foreach ([
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
                    'published_at' => now(),
                    'content' => [
                        $this->brick('hero', [
                            'eyebrow' => 'FriendlyFyzio',
                            'title' => $title,
                            'features' => [],
                            'buttons' => [
                                ['text' => 'Objednat se', 'url' => '/kontakt', 'icon' => 'calendar', 'style' => 'primary'],
                            ],
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
        $img = fn (string $id, string $ixid, string $name): ?int => $this->media(
            "https://images.unsplash.com/{$id}?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid={$ixid}&ixlib=rb-4.1.0&q=80&w=1080",
            $name,
        );

        Page::updateOrCreate(
            ['system_key' => 'home'],
            [
                'slug' => '/',
                'title' => 'Domů',
                'is_system' => true,
                'published_at' => now(),
                'meta_title' => 'Friendly Fyzio – specializovaná fyzioterapie',
                'meta_description' => 'Specializovaná fyzioterapie pro ženy i muže. Těhotenská fyzioterapie, fyzioterapie pánevního dna, pohybové kurzy a masáže s individuálním přístupem.',
                'content' => [
                    $this->brick('hero', [
                        'eyebrow' => 'FriendlyFyzio',
                        'title' => 'Specializovaná fyzioterapie',
                        'features' => '<ul><li>Těhotenská fyzioterapie</li><li>Fyzioterapie pánevního dna</li><li>Fyzioterapie čelistního kloubu</li><li>Fyzioterapie jizev</li></ul>',
                        'image' => $img('photo-1645005512964-5057008b4425', 'M3w4NDM0ODN8MHwxfHJhbmRvbXx8fHx8fHx8fDE3NzUyMjMyMTR8', 'home-hero'),
                        'buttons' => [
                            ['text' => 'Objednat vstupní vyšetření', 'url' => '/kontakt', 'icon' => 'calendar', 'style' => 'primary'],
                            ['text' => 'Chci na masáž', 'url' => '/sluzby', 'icon' => 'sparkles', 'style' => 'primary'],
                            ['text' => 'Koupit dárkový poukaz', 'url' => '/sluzby', 'icon' => 'gift', 'style' => 'outline'],
                        ],
                    ]),
                    $this->brick('last-minute', [
                        'eyebrow' => 'Volné dnes a zítra',
                        'title' => 'Last-minute termíny',
                        'button_text' => 'Zobrazit celý kalendář',
                        'button_url' => '/kontakt',
                        'therapists' => [
                            ['name' => 'Mgr. Jana Ficková', 'role' => 'Fyzioterapeutka', 'slots' => ['Dnes 14:00', 'Dnes 15:30', 'Zítra 9:00']],
                            ['name' => 'Bc. Petra Nová', 'role' => 'Fyzioterapeutka', 'slots' => ['Dnes 16:00', 'Zítra 10:30']],
                            ['name' => 'Mgr. Lucie Malá', 'role' => 'Terapeutka', 'slots' => ['Zítra 8:00', 'Zítra 11:00', 'Zítra 13:30']],
                            ['name' => 'Bc. Eva Horká', 'role' => 'Masérka', 'slots' => ['Dnes 17:00', 'Zítra 12:00']],
                        ],
                    ]),
                    $this->brick('cta-banner', [
                        'eyebrow' => 'Aktuálně přihlašujeme',
                        'title' => 'Přihlašování na období leden – duben 2026',
                        'subtitle' => 'Vyberte si z nabídky pohybových kurzů vedených našimi fyzioterapeutkami. Hormonální jóga, SM systém, kurzy pro těhotné a další.',
                        'buttons' => [
                            ['text' => 'Prohlížet kurzy', 'style' => 'white', 'link_type' => 'custom', 'url' => '/kurzy'],
                            ['text' => 'Prohlížet workshopy', 'style' => 'white', 'link_type' => 'custom', 'url' => '/kurzy'],
                        ],
                    ]),
                    $this->brick('category-cards', [
                        'eyebrow' => 'Aktuální nabídka',
                        'title' => 'Právě přihlašujeme',
                        'subtitle' => 'Vyberte si kategorii a prozkoumejte aktuálně otevřené kurzy.',
                        'button_text' => 'Zobrazit všechny kurzy',
                        'button_url' => '/kurzy',
                        'categories' => [
                            ['icon' => 'activity', 'title' => 'Pohybové kurzy', 'subtitle' => '4 otevřené kurzy', 'url' => '/kurzy', 'items' => [
                                ['label' => 'Hormonální jóga', 'meta' => 'Začíná 12. 1. 2026', 'url' => '/kurzy'],
                                ['label' => 'Somatická jóga', 'meta' => 'Začíná 19. 1. 2026', 'url' => '/kurzy'],
                                ['label' => 'Cvičení pro těhotné', 'meta' => 'Začíná 26. 1. 2026', 'url' => '/kurzy'],
                                ['label' => 'Jin jóga', 'meta' => 'Začíná 2. 2. 2026', 'url' => '/kurzy'],
                            ]],
                            ['icon' => 'users', 'title' => 'Workshopy', 'subtitle' => '3 workshopy', 'url' => '/kurzy', 'items' => [
                                ['label' => 'Workshop zdravých zad', 'meta' => '17. 1. 2026 · 9:00', 'url' => '/kurzy'],
                                ['label' => 'Workshop pánevního dna', 'meta' => '24. 1. 2026 · 9:00', 'url' => '/kurzy'],
                                ['label' => 'Workshop dechových technik', 'meta' => '31. 1. 2026 · 9:00', 'url' => '/kurzy'],
                            ]],
                            ['icon' => 'clock', 'title' => 'Jednorázové lekce', 'subtitle' => '4 lekce', 'url' => '/kurzy', 'items' => [
                                ['label' => 'Konzultace dechových technik', 'meta' => 'Volné termíny', 'url' => '/kurzy'],
                                ['label' => 'Mobilita kyčlí', 'meta' => 'Volné termíny', 'url' => '/kurzy'],
                                ['label' => 'Posílení středu těla', 'meta' => 'Volné termíny', 'url' => '/kurzy'],
                                ['label' => 'Relaxační lekce s rolery', 'meta' => 'Volné termíny', 'url' => '/kurzy'],
                            ]],
                        ],
                    ]),
                    $this->brick('service-cards', [
                        'eyebrow' => 'Naše služby',
                        'title' => 'Naše nabídka',
                        'subtitle' => 'Nabízíme komplexní péči o váš pohybový systém s důrazem na individuální přístup a specializaci na ženské zdraví.',
                        'background' => 'white',
                        'columns' => 3,
                        'link_text' => 'Zjistit více',
                    ]),
                    $this->brick('testimonials', [
                        'eyebrow' => 'Reference',
                        'title' => 'Co říkají naši klienti',
                        'subtitle' => 'Pečujeme o každého klienta a jejich spokojenost je pro nás prioritou.',
                        'background' => 'alt',
                        'items' => [
                            ['quote' => 'Po terapii u paní Fickerové se konečně cítím bez bolesti zad. Individuální přístup a profesionální péče. Vřele doporučuji každému!', 'author' => 'Jana Nováková', 'role' => 'Klientka fyzioterapie', 'avatar' => $img('photo-1684849937281-906b2432e6fa', 'M3w4NDM0ODN8MHwxfHJhbmRvbXx8fHx8fHx8fDE3NzUyMjMyMTd8', 'home-testimonial-jana')],
                            ['quote' => 'Kurzy hormonální jógy mi pomohly s hormonální nerovnováhou. Skvělá atmosféra a profesionální vedení.', 'author' => 'Petra Svobodová', 'role' => 'Účastnice kurzu jógy', 'avatar' => $img('photo-1749535482013-48d9aedf6de5', 'M3w4NDM0ODN8MHwxfHJhbmRvbXx8fHx8fHx8fDE3NzUyMjMyMTh8', 'home-testimonial-petra')],
                            ['quote' => 'Relaxační masáže jsou fantastické. Konečně jsem našla místo, kde se o mě opravdu starají. Děkuji celé FriendlyFyzio!', 'author' => 'Markéta Králová', 'role' => 'Klientka masáží', 'avatar' => $img('photo-1625689871393-daa51f96633c', 'M3w4NDM0ODN8MHwxfHJhbmRvbXx8fHx8fHx8fDE3NzUyMjMyMTh8', 'home-testimonial-marketa')],
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
                        'images' => array_values(array_filter([
                            $img('photo-1612676244045-b3907a062c59', 'M3w4NDM0ODN8MHwxfHJhbmRvbXx8fHx8fHx8fDE3NzUyMjMyMjZ8', 'home-instagram-1'),
                            $img('photo-1539794830467-1f1755804d13', 'M3w4NDM0ODN8MHwxfHJhbmRvbXx8fHx8fHx8fDE3NzUyMjMyMjZ8', 'home-instagram-2'),
                            $img('photo-1774082918671-5785138821c2', 'M3w4NDM0ODN8MHwxfHJhbmRvbXx8fHx8fHx8fDE3NzUyMjMyMjd8', 'home-instagram-3'),
                            $img('photo-1609858922179-97a3c4980b80', 'M3w4NDM0ODN8MHwxfHJhbmRvbXx8fHx8fHx8fDE3NzUyMjMyMjh8', 'home-instagram-4'),
                        ])),
                        'cta_link_type' => 'custom',
                        'cta_url' => 'https://instagram.com',
                        'cta_text' => 'Sledovat na Instagramu',
                    ]),
                    $this->brick('newsletter', [
                        'title' => 'Přihlaste se k odběru novinek',
                        'subtitle' => 'Chcete se dozvědět o kurzech, workshopech a novinkách jako první?',
                        'placeholder' => 'Váš e-mail',
                        'button_text' => 'Odebírat',
                        'consent' => 'Odesláním souhlasím se zpracováním osobních údajů.',
                    ]),
                ],
            ],
        );
    }

    private function aboutPage(): void
    {
        Page::updateOrCreate(
            ['system_key' => 'o-nas'],
            [
                'slug' => 'o-nas',
                'title' => 'O nás',
                'is_system' => true,
                'published_at' => now(),
                'meta_title' => 'O nás – FriendlyFyzio',
                'meta_description' => 'Seznamte se s naším týmem. Naše terapeutky a lektorky jsou odbornice s dlouholetou praxí se specializací na ženské zdraví, těhotenství a pohybové kurzy.',
                'content' => [
                    // The team grid is data-driven — it lists all therapists and links
                    // the published profiles. Fill profiles in the "Terapeuti" resource.
                    $this->brick('team', [
                        'eyebrow' => 'Náš tým',
                        'title' => 'Seznamte se s naším týmem',
                        'subtitle' => 'Naše terapeutky a lektorky jsou odbornice s dlouholetou praxí, které se neustále vzdělávají.',
                        'background' => 'alt',
                        'columns' => 4,
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
