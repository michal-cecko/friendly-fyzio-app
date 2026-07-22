<?php

namespace Database\Seeders;

use App\Models\EventCategory;
use App\Models\Page;
use App\Support\Settings;
use Database\Seeders\Concerns\ImportsMedia;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    use ImportsMedia;

    public function run(): void
    {
        $this->homepage();
        $this->aboutPage();
        $this->contactPage();
        $this->pricingPage();
        $this->coursesPage();
        $this->workshopsPage();
        $this->lessonsPage();
        $this->stornoTermsPage();
        $this->voucherPage();

        // The old "/sluzby" placeholder landing page was retired — the service
        // category pages live at /sluzby/{category} and don't need an index.
        Page::where('system_key', 'sluzby')->forceDelete();
    }

    /**
     * The "Pohybové kurzy" archive page: hero + the data-driven course archive
     * brick (category pills, availability, search, pagination — all in the
     * URL, plus the one-off-event cross-sell section) + newsletter signup.
     */
    private function coursesPage(): void
    {
        Page::updateOrCreate(
            ['system_key' => 'kurzy'],
            [
                'slug' => 'kurzy',
                'title' => 'Pohybové kurzy',
                'is_system' => true,
                'published_at' => now(),
                'meta_title' => 'Pohybové kurzy – FriendlyFyzio',
                'meta_description' => 'Vyberte si z pravidelných semestrálních kurzů vedených našimi fyzioterapeutkami. Hormonální jóga, SM systém, kurzy pro těhotné a další.',
                'content' => [
                    $this->brick('hero', [
                        'eyebrow' => 'KURZY',
                        'title' => 'Pohybové kurzy',
                        'features' => '<p>Vyberte si z pravidelných semestrálních kurzů vedených našimi zkušenými fyzioterapeutkami a přizpůsobených všem úrovním. Nechcete se hned vázat? Vyzkoušejte nejdřív jednorázovou lekci.</p>',
                        'image' => $this->media(
                            'https://images.unsplash.com/photo-1542202800305-1d573ea6a890?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&q=80&w=1080',
                            'kurzy-hero',
                        ),
                        'buttons' => [
                            ['text' => 'Prohlédnout nabídku', 'link_type' => 'custom', 'url' => '#kurzy-archiv', 'icon' => 'calendar', 'style' => 'primary'],
                        ],
                    ]),
                    $this->brick('course-archive', [
                        'eyebrow' => 'Aktuální nabídka',
                        'title' => 'Naše kurzy',
                        'subtitle' => 'Filtrujte podle kategorie a najděte kurz, který vám vyhovuje.',
                        'show_filters' => true,
                        'show_search' => true,
                        'cross_sell' => true,
                        'cross_sell_title' => 'Chcete si to nejdřív vyzkoušet?',
                        'cross_sell_text' => 'Přijďte na jednorázovou lekci bez závazku celého kurzu.',
                        'cross_sell_category' => 'jednorazove-lekce',
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

    /**
     * The "Workshopy" archive page — the custom Mason page of the Workshopy
     * event category (pageable), served at the category URL: hero + the
     * event archive brick pinned to the category + newsletter signup.
     */
    private function workshopsPage(): void
    {
        $category = EventCategory::query()->where('slug', 'workshopy')->first();

        $page = Page::updateOrCreate(
            ['system_key' => 'workshopy'],
            [
                'slug' => 'workshopy',
                'title' => 'Workshopy',
                'is_system' => true,
                'published_at' => now(),
                'meta_title' => 'Workshopy a vzdělávací akce – FriendlyFyzio',
                'meta_description' => 'Jednorázové vzdělávací akce otevřené široké veřejnosti. Workshopy vedou naše fyzioterapeutky i externí lektoři.',
                'content' => [
                    $this->brick('hero', [
                        'eyebrow' => 'WORKSHOPY & VZDĚLÁVÁNÍ',
                        'title' => 'Workshopy a vzdělávací akce',
                        'features' => '<p>Jednorázové vzdělávací akce otevřené široké veřejnosti. Workshopy vedou naše fyzioterapeutky i externí lektoři z příbuzných oborů — psychologie, výživy, mindfulness či somatiky. Žádné odborné vzdělání není potřeba.</p>',
                        'image' => $this->media(
                            'https://images.unsplash.com/photo-1591291621164-2c6367723315?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&q=80&w=1080',
                            'workshopy-hero',
                        ),
                        'buttons' => [
                            ['text' => 'Prohlédnout workshopy', 'link_type' => 'custom', 'url' => '#akce-archiv', 'icon' => 'calendar', 'style' => 'primary'],
                        ],
                    ]),
                    $this->brick('event-archive', [
                        'eyebrow' => 'Otevřené veřejnosti',
                        'title' => 'Aktuální workshopy',
                        'subtitle' => 'Vzdělávací akce pro všechny — pod vedením interních fyzioterapeutek i pozvaných externích lektorů.',
                        'category' => 'workshopy',
                        'show_search' => true,
                        'show_past' => true,
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

        // pageable_* are not mass assignable on Page — attach via the relation.
        $this->attachToCategory($page, $category);
    }

    /**
     * The "Jednorázové lekce" page — the custom Mason page of the
     * jednorazove-lekce event category (pageable), served at the category URL:
     * hero + the event archive brick pinned to the category + newsletter.
     */
    private function lessonsPage(): void
    {
        $category = EventCategory::query()->where('slug', 'jednorazove-lekce')->first();

        $page = Page::updateOrCreate(
            ['system_key' => 'jednorazove-lekce'],
            [
                'slug' => 'jednorazove-lekce',
                'title' => 'Jednorázové lekce',
                'is_system' => true,
                'published_at' => now(),
                'meta_title' => 'Jednorázové lekce – FriendlyFyzio',
                'meta_description' => 'Vyzkoušejte si cvičení bez závazku celého kurzu. Jednorázové vstupy na lekce vedené našimi fyzioterapeutkami — hormonální jóga, SM systém a další.',
                'content' => [
                    $this->brick('hero', [
                        'eyebrow' => 'JEDNORÁZOVÉ LEKCE',
                        'title' => 'Vyzkoušejte lekci bez závazku',
                        'features' => '<p>Nechcete se hned vázat na celý semestrální kurz? Přijďte na jednorázovou lekci — jeden vstup na jedno cvičení pod vedením našich fyzioterapeutek. Pokud vás lekce nadchne, můžete kdykoliv plynule přejít na celou sérii.</p>',
                        'image' => $this->media(
                            'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&q=80&w=1080',
                            'jednorazove-lekce-hero',
                        ),
                        'buttons' => [
                            ['text' => 'Prohlédnout termíny', 'link_type' => 'custom', 'url' => '#akce-archiv', 'icon' => 'calendar', 'style' => 'primary'],
                        ],
                    ]),
                    $this->brick('event-archive', [
                        'eyebrow' => 'Volné termíny',
                        'title' => 'Aktuální jednorázové lekce',
                        'subtitle' => 'Vyberte si termín, který vám vyhovuje — kapacity jsou aktuální.',
                        'category' => 'jednorazove-lekce',
                        'show_search' => true,
                        'show_past' => true,
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

        $this->attachToCategory($page, $category);
    }

    /**
     * Make the page the category's custom page (served at the category URL).
     * The pageable_* morph columns are not mass assignable, so updateOrCreate
     * cannot set them — associate through the relation instead.
     */
    private function attachToCategory(Page $page, ?EventCategory $category): void
    {
        if ($category === null || $page->pageable_id === $category->getKey()) {
            return;
        }

        $page->pageable()->associate($category);
        $page->save();
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
                            ['text' => 'Objednat vstupní vyšetření', 'url' => '/rezervace', 'icon' => 'calendar', 'style' => 'primary'],
                            ['text' => 'Chci na masáž', 'url' => '/sluzby/relaxace', 'icon' => 'sparkles', 'style' => 'primary'],
                            // TODO: point at /darkove-poukazy once the voucher page ships (backlog B2).
                            ['text' => 'Koupit dárkový poukaz', 'url' => '/kontakt', 'icon' => 'gift', 'style' => 'outline'],
                        ],
                    ]),
                    $this->brick('last-minute', [
                        'eyebrow' => 'Volné dnes a zítra',
                        'title' => 'Last-minute termíny',
                        'button_text' => 'Zobrazit celý kalendář',
                        'button_url' => '/rezervace',
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
                    $this->brick('enrolling-now', [
                        'eyebrow' => 'Aktuální nabídka',
                        'title' => 'Právě přihlašujeme',
                        'subtitle' => 'Vyberte si kategorii a prozkoumejte aktuálně otevřené kurzy.',
                        'text' => 'Zobrazit všechny kurzy',
                        'style' => 'outline',
                        'icon' => 'arrow-right',
                        'link_type' => 'custom',
                        'url' => '/kurzy',
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
                    // Jakub is an external collaborator, not a core team member,
                    // so he's presented as a static block below the data-driven
                    // team grid rather than as a bookable therapist profile.
                    $this->brick('photo-text', [
                        'eyebrow' => 'Externí spolupráce',
                        'image' => $this->mediaFromPath(database_path('seeders/data/team/jakub-trepac.jpg'), 'Jakub Trepáč'),
                        'image_position' => 'right',
                        'title' => 'Mgr. Jakub Trepáč',
                        'body' => '<p>Externí spolupracovník našeho centra se specializací na <strong>dětskou fyzioterapii</strong>.</p>',
                    ]),
                ],
            ],
        );
    }

    private function contactPage(): void
    {
        Page::updateOrCreate(
            ['system_key' => 'kontakt'],
            [
                'slug' => 'kontakt',
                'title' => 'Kontakt',
                'is_system' => true,
                'published_at' => now(),
                'meta_title' => 'Kontakt – FriendlyFyzio',
                'meta_description' => 'Napište nám nebo se objednejte. Najdete nás na adrese Zednická 1109/2, Ostrava. Těšíme se na vás.',
                'content' => [
                    $this->brick('contact', [
                        'title' => 'Kontakt',
                        'subtitle' => 'Máte dotaz nebo se chcete objednat? Napište nám, rádi vám odpovíme.',
                        'form_title' => 'Napište nám',
                        'form_button_text' => 'Odeslat zprávu',
                    ]),
                ],
            ],
        );
    }

    private function pricingPage(): void
    {
        Page::updateOrCreate(
            ['system_key' => 'cenik'],
            [
                'slug' => 'cenik',
                'title' => 'Ceník',
                'is_system' => true,
                'published_at' => now(),
                'meta_title' => 'Ceník – FriendlyFyzio',
                'meta_description' => 'Přehled cen fyzioterapie, masáží, přístrojové terapie i ostatních služeb a poplatků. Ceny jsou uvedeny včetně DPH.',
                'content' => [
                    $this->brick('page-intro', [
                        'title' => 'Ceník služeb',
                        'subtitle' => 'Přehled cen našich služeb. Ceny jsou uvedeny včetně DPH.',
                    ]),
                    $this->brick('price-list', [
                        'categories' => [
                            [
                                'label' => 'Fyzioterapie a kurzy',
                                'heading' => 'Fyzioterapie',
                                'rows' => [
                                    ['name' => 'Vstupní vyšetření', 'note' => '90 min', 'price' => '1 750 Kč'],
                                    ['name' => 'Kontrolní / návazná vyšetření', 'note' => '60 min', 'price' => '1 250 Kč'],
                                    ['name' => 'Terapie pánevního dna (vstupní)', 'note' => '90 min', 'price' => '1 300 Kč'],
                                    ['name' => 'Kontrolní terapie pánevního dna', 'note' => '60 min', 'price' => '850 Kč'],
                                    ['name' => 'Těhotenská fyzioterapie', 'note' => '90 min', 'price' => '1 300 Kč'],
                                    ['name' => 'Pohybové kurzy', 'note' => 'dle rozvrhu', 'price' => 'od 200 Kč / lekce'],
                                ],
                            ],
                            [
                                'label' => 'Masáže',
                                'heading' => 'Masáže a relaxace',
                                'rows' => [
                                    ['name' => 'Masáž obličeje, šíje a krku', 'note' => '60 min', 'price' => '1 000 Kč'],
                                    ['name' => 'Těhotenská masáž', 'note' => '60 min', 'price' => '1 000 Kč'],
                                    ['name' => 'Těhotenská masáž', 'note' => '90 min', 'price' => '1 400 Kč'],
                                    ['name' => 'Masáž miminek a dětí do 5 let', 'note' => '30 min', 'price' => '500 Kč'],
                                    ['name' => 'Bylinná napářka s relaxací', 'note' => 'cca 60 min', 'price' => '1 200 Kč'],
                                ],
                            ],
                            [
                                'label' => 'Laser / kryo',
                                'heading' => 'Laser / kryo / přístrojová terapie',
                                'rows' => [
                                    ['name' => 'Laserová terapie', 'note' => '30 min', 'price' => '500 Kč'],
                                    ['name' => 'Kryoterapie', 'note' => '15 min', 'price' => '490 Kč'],
                                ],
                            ],
                            [
                                'label' => 'Ostatní',
                                'heading' => 'Ostatní služby a poplatky',
                                'rows' => [
                                    ['name' => 'Skupinové cvičení', 'note' => 'dle rozvrhu', 'price' => '190 Kč'],
                                    ['name' => 'Náhradní termín / pozdní příchod', 'note' => '', 'price' => 'od 500 Kč'],
                                    ['name' => 'Zrušení termínu méně než 24 h předem', 'note' => '', 'price' => '50 % ceny'],
                                    ['name' => 'Storno poplatek (neomluvená absence)', 'note' => '', 'price' => '100 % ceny'],
                                    ['name' => 'Cestovné (návštěva u klienta)', 'note' => 'dle vzdálenosti', 'price' => '200 Kč'],
                                ],
                            ],
                        ],
                        'note' => '<p>Ceny jsou orientační a mohou se lišit podle individuálních potřeb. Platit lze v hotovosti i kartou na místě.</p>',
                    ]),
                ],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    /**
     * The "Storno podmínky" page — linked from the mandatory terms checkbox in
     * both booking flows. The therapy/massage policy is the clinic's binding
     * wording; the course/lesson/workshop cancellation windows are summarised
     * from the current settings so the page stays in sync with admin config.
     */
    private function stornoTermsPage(): void
    {
        $feePercent = Settings::stornoFeePercent();
        $noShowPercent = Settings::noShowFeePercent();
        $courseDays = Settings::courseCancelBeforeDays();
        $eventHours = Settings::eventCancelBeforeHours();

        Page::updateOrCreate(
            ['system_key' => 'storno-podminky'],
            [
                'slug' => 'storno-podminky',
                'title' => 'Storno podmínky',
                'is_system' => true,
                'published_at' => now(),
                'meta_title' => 'Storno podmínky – FriendlyFyzio',
                'meta_description' => 'Podmínky pro zrušení termínů fyzioterapie, masáží, kurzů, lekcí a workshopů ve FriendlyFyzio.',
                'content' => [
                    $this->brick('page-intro', [
                        'title' => 'Storno podmínky',
                        'subtitle' => 'Domluvené termíny jsou pro nás i pro vás závazné. Níže najdete pravidla pro jejich zrušení.',
                    ]),
                    $this->brick('callout', [
                        'icon' => 'triangle-alert',
                        'title' => 'Fyzioterapie a masáže',
                        'body' => '<p>Vážení klienti, domluvené termíny jsou závazné. Omluva po 17. hodině předchozího dne nebo v den terapie je přijímána pouze ze zdravotních důvodů potvrzených lékařem.</p>',
                        'note' => "<p>V opačném případě je klient povinen uhradit {$noShowPercent} % z ceny terapie. Děkujeme za pochopení.</p>",
                    ]),
                    $this->brick('rich-text', [
                        'content' => implode('', [
                            '<h2>Kurzy a jednorázové akce</h2>',
                            '<p>Přihlášky na kurzy a jednorázové akce (lekce, workshopy) se hradí předem (QR platbou). Nezaplacená přihláška po uplynutí rezervační lhůty automaticky propadá a místo nabídneme dalším zájemcům.</p>',
                            '<ul>',
                            "<li><strong>Pohybové kurzy</strong> – odhlásit se můžete nejpozději {$courseDays} dní před začátkem série.</li>",
                            "<li><strong>Jednorázové akce</strong> (lekce, workshopy) – odhlásit se můžete nejpozději {$eventHours} hodin před konáním.</li>",
                            '</ul>',
                            "<p>Při pozdějším zrušení termínu fyzioterapie nebo masáže, u kterého již platí storno lhůta, účtujeme storno poplatek ve výši {$feePercent} % z ceny. Po uplynutí storno lhůty už zrušení online není možné – kontaktujte nás prosím telefonicky.</p>",
                            '<h2>Náhradní vstupy u kurzů</h2>',
                            '<p>Pokud se z konkrétní lekce kurzu omluvíte včas, vystavíme vám náhradní vstup, který uplatníte na volné místo v souběžné skupině. Detaily najdete ve své <a href="/muj-ucet/nahrady">klientské zóně</a>.</p>',
                        ]),
                    ]),
                ],
            ],
        );
    }

    /**
     * The "Dárkové poukazy" page — restores the old live `/darkove-poukazy` URL.
     * Placeholder for now: the SimpleShop voucher e-shop iframe drops in here
     * (backlog B2).
     */
    private function voucherPage(): void
    {
        Page::updateOrCreate(
            ['system_key' => 'darkove-poukazy'],
            [
                'slug' => 'darkove-poukazy',
                'title' => 'Dárkové poukazy',
                'is_system' => true,
                'published_at' => now(),
                'meta_title' => 'Dárkové poukazy – FriendlyFyzio',
                'meta_description' => 'Darujte fyzioterapii, masáž nebo relaxaci. Dárkové poukazy FriendlyFyzio na míru.',
                'content' => [
                    $this->brick('page-intro', [
                        'title' => 'Dárkové poukazy',
                        'subtitle' => 'Darujte zdraví a relaxaci svým blízkým.',
                    ]),
                    $this->brick('rich-text', [
                        'content' => '<p>TODO pridaj sem iframe na eshop darčekových poukazov</p>',
                    ]),
                ],
            ],
        );
    }

    private function brick(string $id, array $config): array
    {
        return ['type' => 'masonBrick', 'attrs' => ['id' => $id, 'config' => $config]];
    }
}
