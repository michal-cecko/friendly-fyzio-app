<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceCategory;
use Database\Seeders\Concerns\ImportsMedia;
use Illuminate\Database\Seeder;

/**
 * Attaches the physiotherapy custom Mason pages from the Pencil designs: the
 * Fyzioterapie category landing page and the two topic marketing pages
 * ("Terapie pánevního dna", "Těhotenská fyzioterapie", on their real vstupní
 * services seeded by DemoSeeder). Rendered at /sluzby/{category}[/{service}].
 * Skips gracefully if the owning record is absent.
 */
class ServicePagesSeeder extends Seeder
{
    use ImportsMedia;

    public function run(): void
    {
        $this->fyzioterapieCategoryPage();
        $this->pelvicFloorPage();
        $this->pregnancyPage();
    }

    private function fyzioterapieCategoryPage(): void
    {
        $category = ServiceCategory::where('slug', 'fyzioterapie')->first();

        if ($category === null) {
            return;
        }

        $img = fn (string $id, string $name): ?int => $this->media(
            "https://images.unsplash.com/{$id}?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&q=80&w=1080",
            $name,
        );

        $topics = [
            ['title' => 'Terapie pánevního dna', 'image' => $img('photo-1717500252172-b1840ea64f05', 'fyzio-topic-panevni-dno'), 'url' => '/sluzby/fyzioterapie/terapie-panevniho-dna', 'description' => 'Terapie neplodných párů, opakovaných potratů, inkontinence, poklesu orgánů malé pánve, bolestivé nebo nepravidelné menstruace, kostrčového syndromu, výhřezu plotének.'],
            ['title' => 'Těhotenská fyzioterapie', 'image' => $img('photo-1671493235081-5842463637cd', 'fyzio-topic-tehotenska'), 'url' => '/sluzby/fyzioterapie/tehotenska-fyzioterapie', 'description' => 'Kondiční a uvolňující cvičení pro těhotné ženy, prevence negativních důsledků těhotenství, příprava na porod, terapie poporodní diastázy a dalších následků porodu a těhotenství.'],
            ['title' => 'Terapie jizev', 'image' => $img('photo-1758691462413-b07dee2933fe', 'fyzio-topic-jizvy'), 'url' => '/rezervace', 'description' => 'Péče o jizvy po císařských řezech, nástřizích hráze, laparoskopiích, plastikách…ty čerstvé i ty zapomenuté. Ošetření jizev v domácím prostředí nebo přímo v nemocnici.'],
            ['title' => 'Terapie čelistního kloubu', 'image' => $img('photo-1576770075856-86b01944b92b', 'fyzio-topic-celist'), 'url' => '/rezervace', 'description' => 'Dysfunkce čelistního kloubu mohou být příčinou mnoha obtíží, např. bolestí hlavy, dechových obtíží, bolestí krční páteře a ramenních pletenců nebo i dysfunkcí svalů pánevního dna.'],
        ];

        $category->customPage()->updateOrCreate([], [
            'title' => 'Fyzioterapie',
            'slug' => 'fyzioterapie-vlastni-stranka',
            'published_at' => now(),
            'content' => [
                $this->brick('hero', [
                    'eyebrow' => 'Specializace na ženské zdraví',
                    'title' => 'Fyzioterapie',
                    'features' => '<p>Obor fyzioterapie se zabývá diagnostikou, léčbou a prevencí dysfunkcí pohybového systému. Vyšetřujeme jedince jako celek a propojujeme vědomosti z mnoha medicínských oborů. Specializujeme se zejména na fyzioterapii žen a pánevního dna.</p>',
                    'image' => $img('photo-1650044252595-cacd425982ff', 'fyzio-category-hero'),
                    'buttons' => [
                        ['text' => 'Objednat se', 'url' => '/rezervace', 'icon' => 'calendar', 'style' => 'primary'],
                        ['text' => 'Více informací', 'url' => '#oblasti', 'icon' => 'arrow-down', 'style' => 'outline'],
                    ],
                ]),
                $this->brick('cards', [
                    'eyebrow' => 'Na co se zaměřujeme',
                    'title' => 'Oblasti naší specializace',
                    'subtitle' => 'Naše fyzioterapeutky se specializují na široké spektrum potíží pohybového aparátu.',
                    'background' => 'white',
                    'columns' => 4,
                    'cards' => array_map(fn (array $t): array => [
                        'image' => $t['image'],
                        'title' => $t['title'],
                        'description' => $t['description'],
                        'text' => 'Více informací',
                        'style' => 'text',
                        'url' => $t['url'],
                    ], $topics),
                ]),
                $this->brick('steps', [
                    'eyebrow' => 'Jak to probíhá',
                    'title' => 'Cesta k vaší rehabilitaci',
                    'subtitle' => 'Od první návštěvy až po plný návrat k aktivnímu životu.',
                    'steps' => [
                        ['icon' => 'clipboard-list', 'title' => 'Vstupní vyšetření', 'description' => 'Komplexní 90minutové vyšetření vašeho stavu a potřeb.'],
                        ['icon' => 'stethoscope', 'title' => 'Diagnostika', 'description' => 'Stanovení diagnózy a individuální plán terapie.'],
                        ['icon' => 'activity', 'title' => 'Terapie', 'description' => 'Pravidelná terapeutická setkání a cvičení podle plánu.'],
                        ['icon' => 'check-circle', 'title' => 'Výsledky', 'description' => 'Kontrolní vyšetření a návrat k aktivnímu životu.'],
                    ],
                ]),
                $this->brick('pricing', [
                    'eyebrow' => 'Ceník',
                    'title' => 'Ceník fyzioterapie',
                    'subtitle' => 'Přehled cen našich fyzioterapeutických služeb. Vstupní vyšetření trvá 90 minut. Lze využít těhotenské a poporodní příspěvky z pojišťovny.',
                    'rows' => [
                        ['name' => 'Vstupní vyšetření', 'note' => '90 min', 'price' => '1 750 Kč'],
                        ['name' => 'Kontrolní / návazná vyšetření', 'note' => '60 min', 'price' => '1 250 Kč'],
                    ],
                    'buttons' => [
                        ['text' => 'Objednat se', 'url' => '/rezervace', 'icon' => 'calendar', 'style' => 'primary'],
                    ],
                ]),
                $this->brick('testimonials', [
                    'eyebrow' => 'Co říkají naši klienti',
                    'title' => 'Recenze klientů',
                    'subtitle' => 'Přečtěte si, jak naše terapie pomohly našim klientkám a klientům.',
                    'background' => 'alt',
                    'items' => [
                        ['quote' => 'Po prvním porodu jsem měla problémy s pánevním dnem. Po několika sezeních u Friendly Fyzio se vše vrátilo do normálu. Děkuji!', 'author' => 'Markéta K.', 'role' => 'Terapie pánevního dna'],
                        ['quote' => 'Během těhotenství mě trápily silné bolesti zad. Fyzioterapie mi nejen ulevila, ale připravila mě i na porod. Skvělý přístup!', 'author' => 'Tereza N.', 'role' => 'Těhotenská fyzioterapie'],
                        ['quote' => 'Jizva po císařském řezu mě trápila roky. Po terapii jizev se konečně cítím komfortně a bez omezení. Vřele doporučuji!', 'author' => 'Lucie V.', 'role' => 'Terapie jizev'],
                    ],
                ]),
                $this->brick('cta-banner', [
                    'title' => 'Chcete se objednat na vstupní vyšetření?',
                    'subtitle' => 'První krok k vaší rehabilitaci. Objednejte se online nebo nás kontaktujte telefonicky.',
                    'buttons' => [
                        ['text' => 'Rezervovat termín', 'url' => '/rezervace', 'icon' => 'calendar', 'style' => 'white'],
                    ],
                ]),
                $this->brick('instagram', [
                    'eyebrow' => 'Sledujte nás',
                    'title' => '@friendlyfyzio',
                    'subtitle' => 'Nahlédněte do našeho dění, sledujte tipy na cvičení a novinky z naší kliniky.',
                    'handle' => '@friendlyfyzio',
                    'images' => array_values(array_filter([
                        $img('photo-1612676244045-b3907a062c59', 'home-instagram-1'),
                        $img('photo-1539794830467-1f1755804d13', 'home-instagram-2'),
                        $img('photo-1774082918671-5785138821c2', 'home-instagram-3'),
                        $img('photo-1609858922179-97a3c4980b80', 'home-instagram-4'),
                    ])),
                    'cta_link_type' => 'custom',
                    'cta_url' => 'https://instagram.com',
                    'cta_text' => 'Sledovat na Instagramu',
                ]),
            ],
        ]);
    }

    private function pelvicFloorPage(): void
    {
        $service = Service::where('slug', 'terapie-panevniho-dna')->first();

        if ($service === null) {
            return;
        }

        $this->customPage($service, 'Terapie pánevního dna', [
            $this->brick('hero', [
                'eyebrow' => 'Fyzioterapie',
                'title' => 'Terapie pánevního dna',
                'features' => '<p>Pánevní dno je skupina svalů, které podporují orgány malé pánve. Jejich oslabení může vést k inkontinenci, prolapsům a bolestivosti. Naše specializovaná terapie vám pomůže znovu získat kontrolu a zbavit se obtíží.</p>',
                'buttons' => [
                    ['text' => 'Objednat se na terapii', 'url' => '/rezervace', 'icon' => 'calendar', 'style' => 'primary'],
                    ['text' => 'Více informací', 'url' => '#o-terapii', 'icon' => 'arrow-down', 'style' => 'outline'],
                ],
            ]),
            $this->brick('section-heading', [
                'title' => 'O terapii pánevního dna',
            ]),
            $this->brick('rich-text', [
                'content' => '<p>Terapie pánevního dna je specializovaná rehabilitace zaměřená na svaly, vazy a pojivové tkáně v oblasti pánve. Tyto struktury hrají klíčovou roli při udržení kontinence, stabilitě páteře a sexuálních funkcích.</p><p>Naše terapeutky používají kombinaci manuálních technik, speciálních cvičení a edukace, aby vám pomohly dosáhnout optimálních výsledků. Každý terapeutický plán je individuálně přizpůsoben vašim potřebám a cílům.</p>',
            ]),
            $this->brick('feature-cards', [
                'eyebrow' => 'Příznaky a indikace',
                'title' => 'Komu pomůže terapie pánevního dna?',
                'columns' => 2,
                'cards' => [
                    [
                        'icon' => 'alert-circle',
                        'title' => 'Kdy vyhledat pomoc?',
                        'description' => '<ul><li>Únik moči při kýchání, smíchu, běhání nebo zvedání těžkého (stresová inkontinence)</li><li>Časté a neodkladné nucení na toaletu (urgentní inkontinence)</li><li>Bolesti v oblasti pánve, křčku nebo kostrče</li><li>Pocit tíže nebo tlaku v podbříšku (sestup orgánů)</li><li>Potíže po porodu – diastáza, bolesti, oslabené pánevní dno</li></ul>',
                    ],
                    [
                        'icon' => 'users',
                        'title' => 'Pro koho je terapie určena?',
                        'description' => '<ul><li>Ženy po porodu a v poporodním období</li><li>Ženy v perimenopauze a menopauze</li><li>Muži po operaci prostaty</li><li>Sportovci s přetíženými svaly pánevního dna</li><li>Každý, kdo chce preventivně posilovat pánevní dno</li></ul>',
                    ],
                ],
            ]),
            $this->brick('steps', [
                'eyebrow' => 'Jak to probíhá',
                'title' => 'Cesta k vaší rehabilitaci',
                'subtitle' => 'Od první návštěvy až po plný návrat k aktivnímu životu.',
                'steps' => [
                    ['icon' => 'clipboard-list', 'title' => 'Vstupní vyšetření', 'description' => 'Komplexní 90minutové vyšetření vašeho stavu a potřeb.'],
                    ['icon' => 'stethoscope', 'title' => 'Diagnostika', 'description' => 'Stanovení diagnózy a individuální plán terapie.'],
                    ['icon' => 'activity', 'title' => 'Terapie', 'description' => 'Pravidelná terapeutická setkání a cvičení podle plánu.'],
                    ['icon' => 'check-circle', 'title' => 'Výsledky', 'description' => 'Kontrolní vyšetření a návrat k aktivnímu životu.'],
                ],
            ]),
            $this->brick('pricing', [
                'eyebrow' => 'Ceník',
                'title' => 'Ceník terapie pánevního dna',
                'rows' => [
                    ['name' => 'Terapie pánevního dna (vstupní)', 'note' => '90 min', 'price' => '1 300 Kč'],
                    ['name' => 'Kontrolní terapie pánevního dna', 'note' => '60 min', 'price' => '850 Kč'],
                ],
                'buttons' => [
                    ['text' => 'Objednat se na terapii', 'url' => '/rezervace', 'icon' => 'calendar', 'style' => 'primary'],
                ],
            ]),
            $this->brick('cta-banner', [
                'title' => 'Zdravé pánevní dno je základem celkové pohody a kvality života.',
            ]),
            $this->brick('testimonials', [
                'eyebrow' => 'Co říkají naši klienti',
                'title' => 'Recenze klientů',
                'subtitle' => 'Přečtěte si, jak naše terapie pomohly našim klientkám a klientům.',
                'background' => 'alt',
                'items' => [
                    ['quote' => 'Po prvním porodu jsem měla problémy s pánevním dnem. Po několika sezeních u Friendly Fyzio se vše vrátilo do normálu. Děkuji!', 'author' => 'Markéta K.', 'role' => 'Terapie pánevního dna'],
                    ['quote' => 'Během těhotenství mě trápily silné bolesti zad. Fyzioterapie mi nejen ulevila, ale připravila mě i na porod. Skvělý přístup!', 'author' => 'Tereza N.', 'role' => 'Těhotenská fyzioterapie'],
                    ['quote' => 'Jizva po císařském řezu mě trápila roky. Po terapii jizev se konečně cítím komfortně a bez omezení. Vřele doporučuji!', 'author' => 'Lucie V.', 'role' => 'Terapie jizev'],
                ],
            ]),
            $this->brick('cta-banner', [
                'title' => 'Doporučení pro zdravé pánevní dno',
                'subtitle' => 'Kromě pravidelné terapie doporučujeme denně cvičit pánevní dno alespoň 10 minut. Správná aktivace pánevního dna je klíčová nejen při obtížích, ale i jako prevence. Vyhněte se dlouhému sezení a zvedání těžkých břemen. A pokud si nejste jistí správnou technikou, rádi vás na terapii navedeme.',
                'buttons' => [
                    ['text' => 'Objednat se na terapii', 'url' => '/rezervace', 'icon' => 'calendar', 'style' => 'white'],
                ],
            ]),
        ]);
    }

    private function pregnancyPage(): void
    {
        $service = Service::where('slug', 'tehotenska-fyzioterapie')->first();

        if ($service === null) {
            return;
        }

        $this->customPage($service, 'Těhotenská fyzioterapie', [
            $this->brick('hero', [
                'eyebrow' => 'Fyzioterapie',
                'title' => 'Těhotenská fyzioterapie',
                'features' => '<p>Ženy jsou během těhotenství i po něm vystaveny citelným změnám, které negativně působí na držení těla, ale také na funkci pánevního dna a břišní stěny. Pomůžeme vám projít těhotenstvím bez bolestí.</p>',
                'buttons' => [
                    ['text' => 'Objednat se na terapii', 'url' => '/rezervace', 'icon' => 'calendar', 'style' => 'primary'],
                    ['text' => 'Více informací', 'url' => '#o-terapii', 'icon' => 'arrow-down', 'style' => 'outline'],
                ],
            ]),
            $this->brick('section-heading', [
                'title' => 'O těhotenské fyzioterapii',
            ]),
            $this->brick('rich-text', [
                'content' => '<p>V rámci předporodní přípravy uvolňujeme ženám pánevní dno, a to od 36. týdne těhotenství (pokud není důvod ošetřit dříve). K této přípravě využíváme metody per rectum nebo per vaginam.</p><p>Součástí je ošetření případných jizev v oblasti rodidel z předchozích porodů, hodnocení stavu kyčlí, pánve a břišní stěny. Ženě na modelu pánve ukážeme, k jakým pohybům pánve a kyčlí během porodu dochází a jak je možné ovlivnit poranění hráze.</p>',
            ]),
            $this->brick('feature-cards', [
                'eyebrow' => 'Pro koho je určena',
                'title' => 'Těhotenská fyzioterapie je určená pro ženy, které',
                'columns' => 2,
                'cards' => [
                    [
                        'icon' => 'alert-circle',
                        'title' => 'Obtíže v těhotenství',
                        'description' => '<ul><li>Bolí záda a klouby</li><li>By chtěly bolestem předejít</li><li>Si přejí plynulý a přirozený porod</li><li>Se chtějí naučit pracovat s jizvami po předchozím porodu</li><li>By se rády vyhnuly nástřihu hráze</li></ul>',
                    ],
                    [
                        'icon' => 'heart',
                        'title' => 'Prevence a příprava',
                        'description' => '<ul><li>Chtějí ovlivnit své pánevní dno</li><li>Chtějí předejít poporodnímu rozestupu bříška a diastáze</li><li>Chtějí ovlivnit pozici miminka v děloze</li><li>Se cítí unavené a bříško je táhne k zemi</li><li>Chtějí se připravit na porod</li></ul>',
                    ],
                ],
            ]),
            $this->brick('steps', [
                'eyebrow' => 'Jak to probíhá',
                'title' => 'Průběh těhotenské fyzioterapie',
                'subtitle' => 'Od první konzultace až po přípravu na porod.',
                'steps' => [
                    ['icon' => 'message-circle', 'title' => 'Konzultace', 'description' => 'Úvodní rozhovor o vašem těhotenství, obtížích a přáních.'],
                    ['icon' => 'stethoscope', 'title' => 'Vyšetření', 'description' => 'Vyšetření pánevního dna, kyčlí, pánve a břišní stěny.'],
                    ['icon' => 'activity', 'title' => 'Terapie', 'description' => 'Manuální techniky, uvolnění pánevního dna a příprava na porod.'],
                    ['icon' => 'graduation-cap', 'title' => 'Edukace', 'description' => 'Nácvik pohybů pro porod a prevence poranění hráze.'],
                ],
            ]),
            $this->brick('pricing', [
                'eyebrow' => 'Ceník',
                'title' => 'Ceník těhotenské fyzioterapie',
                'rows' => [
                    ['name' => 'Těhotenská fyzioterapie', 'note' => '90 min', 'price' => '1 300 Kč'],
                    ['name' => 'Kontrolní těhotenská fyzioterapie', 'note' => '60 min', 'price' => '850 Kč'],
                ],
                'buttons' => [
                    ['text' => 'Objednat se na terapii', 'url' => '/rezervace', 'icon' => 'calendar', 'style' => 'primary'],
                ],
            ]),
            $this->brick('cta-banner', [
                'title' => 'Pohodová uvolněná maminka = pohodové a relaxované miminko.',
            ]),
            $this->brick('testimonials', [
                'eyebrow' => 'Co říkají naši klienti',
                'title' => 'Recenze klientů',
                'subtitle' => 'Přečtěte si, jak naše terapie pomohly našim klientkám a klientům.',
                'background' => 'alt',
                'items' => [
                    ['quote' => 'Díky těhotenské fyzioterapii jsem se zbavila bolestí zad a porod proběhl hladce. Doporučuji každé těhulce!', 'author' => 'Petra H.', 'role' => 'Těhotenská fyzioterapie'],
                    ['quote' => 'Příprava na porod mi dodala sebevědomí. Terapeutka mi vše skvěle vysvětlila a porod byl přirozený a klidný.', 'author' => 'Klára M.', 'role' => 'Předporodní příprava'],
                    ['quote' => 'Po prvním porodu jsem měla diastázu. Díky cvičení a terapii se bříško vrátilo do formy. Skvělý přístup!', 'author' => 'Jana S.', 'role' => 'Poporodní rehabilitace'],
                ],
            ]),
            $this->brick('cta-banner', [
                'title' => 'Doporučení pro zdravé těhotenství',
                'subtitle' => 'Pokud vás netrápí žádné z výše zmiňovaných obtíží, není těhotenská fyzioterapie nutná. Doporučujeme však, abyste každý den absolvovaly kondiční procházku alespoň 20 minut. Porod není pasivní záležitost a jedna z věcí vedoucí k plynulému porodu je právě pohyb. A komu procházky nestačí, těm nabízíme naše lekce a kurzy pro všechny těhulky.',
                'buttons' => [
                    ['text' => 'Objednat se', 'url' => '/rezervace', 'icon' => 'calendar', 'style' => 'white'],
                    ['text' => 'Zobrazit kurzy', 'url' => '/kurzy', 'icon' => 'arrow-right', 'style' => 'outline'],
                ],
            ]),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $content
     */
    private function customPage(Service $service, string $title, array $content): void
    {
        $service->customPage()->updateOrCreate([], [
            'title' => $title,
            'slug' => $service->slug.'-vlastni-stranka',
            'published_at' => now(),
            'content' => $content,
        ]);
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
