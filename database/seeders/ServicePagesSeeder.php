<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceCategory;
use Database\Seeders\Concerns\ImportsMedia;
use Illuminate\Database\Seeder;

/**
 * Attaches the custom Mason pages from the Pencil designs: the Fyzioterapie,
 * Relaxace and Přístrojová terapie category landing pages plus their topic/service
 * marketing pages (physiotherapy: pelvic floor, pregnancy; massage: lymphatic,
 * pregnancy, baby and herbal steam; apparatus: laser, cryotherapy — all on their
 * real services seeded by DemoSeeder). Rendered at /sluzby/{category}[/{service}].
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

        $this->relaxaceCategoryPage();
        $this->lymphMassagePage();
        $this->pregnancyMassagePage();
        $this->babyMassagePage();
        $this->herbalSteamPage();

        $this->pristrojovaTerapieCategoryPage();
        $this->cryotherapyPage();
        $this->laserPage();
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

        $img = fn (string $id, string $name): ?int => $this->media(
            "https://images.unsplash.com/{$id}?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&q=80&w=1080",
            $name,
        );

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
            $this->brick('photo-text', [
                'image' => $img('photo-1717500252172-b1840ea64f05', 'fyzio-panevni-about'),
                'image_position' => 'left',
                'title' => 'O terapii pánevního dna',
                'body' => '<p>Terapie pánevního dna je specializovaná rehabilitace zaměřená na svaly, vazy a pojivové tkáně v oblasti pánve. Tyto struktury hrají klíčovou roli při udržení kontinence, stabilitě páteře a sexuálních funkcích.</p><p>Naše terapeutky používají kombinaci manuálních technik, speciálních cvičení a edukace, aby vám pomohly dosáhnout optimálních výsledků. Každý terapeutický plán je individuálně přizpůsoben vašim potřebám a cílům.</p>',
            ]),
            $this->brick('feature-cards', [
                'eyebrow' => 'Příznaky a indikace',
                'title' => 'Komu pomůže terapie pánevního dna?',
                'columns' => 2,
                'background' => 'alt',
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
                'buttons' => [
                    ['text' => 'Objednat se na terapii', 'url' => '/rezervace', 'icon' => 'calendar', 'style' => 'primary'],
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
            $this->brick('quote-banner', [
                'text' => 'Zdravé pánevní dno je základem celkové pohody a kvality života.',
                'icon' => 'heart',
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
            $this->brick('callout', [
                'icon' => 'heart-pulse',
                'title' => 'Doporučení pro zdravé pánevní dno',
                'body' => '<p>Kromě pravidelné terapie doporučujeme denně cvičit pánevní dno alespoň 10 minut. Správná aktivace pánevního dna je klíčová nejen při obtížích, ale i jako prevence. Vyhněte se dlouhému sezení a zvedání těžkých břemen.</p>',
                'note' => 'A pokud si nejste jistí správnou technikou, rádi vás na terapii navedeme.',
                'buttons' => [
                    ['text' => 'Objednat se na terapii', 'url' => '/rezervace', 'icon' => 'calendar', 'style' => 'soft'],
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

        $img = fn (string $id, string $name): ?int => $this->media(
            "https://images.unsplash.com/{$id}?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&q=80&w=1080",
            $name,
        );

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
            $this->brick('photo-text', [
                'image' => $img('photo-1671493235081-5842463637cd', 'fyzio-tehotenska-about'),
                'image_position' => 'left',
                'title' => 'O těhotenské fyzioterapii',
                'body' => '<p>V rámci předporodní přípravy uvolňujeme ženám pánevní dno, a to od 36. týdne těhotenství (pokud není důvod ošetřit dříve). K této přípravě využíváme metody per rectum nebo per vaginam.</p><p>Součástí je ošetření případných jizev v oblasti rodidel z předchozích porodů, hodnocení stavu kyčlí, pánve a břišní stěny. Ženě na modelu pánve ukážeme, k jakým pohybům pánve a kyčlí během porodu dochází a jak je možné ovlivnit poranění hráze.</p>',
            ]),
            $this->brick('feature-cards', [
                'eyebrow' => 'Pro koho je určena',
                'title' => 'Těhotenská fyzioterapie je určená pro ženy, které',
                'columns' => 2,
                'background' => 'alt',
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
                'buttons' => [
                    ['text' => 'Objednat se na terapii', 'url' => '/rezervace', 'icon' => 'calendar', 'style' => 'primary'],
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
            $this->brick('quote-banner', [
                'text' => 'Pohodová uvolněná maminka = pohodové a relaxované miminko.',
                'icon' => 'heart',
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
            $this->brick('callout', [
                'icon' => 'heart-pulse',
                'title' => 'Doporučení pro zdravé těhotenství',
                'body' => '<p>Pokud vás netrápí žádné z výše zmiňovaných obtíží, není těhotenská fyzioterapie nutná. Doporučujeme však, abyste každý den absolvovaly kondiční procházku alespoň 20 minut. Porod není pasivní záležitost a jedna z věcí vedoucí k plynulému porodu je právě pohyb.</p>',
                'note' => 'A komu procházky nestačí, těm nabízíme naše lekce a kurzy pro všechny těhulky.',
                'buttons' => [
                    ['text' => 'Objednat se', 'url' => '/rezervace', 'icon' => 'calendar', 'style' => 'soft'],
                    ['text' => 'Zobrazit kurzy', 'url' => '/kurzy', 'icon' => 'arrow-right', 'style' => 'text'],
                ],
            ]),
        ]);
    }

    private function relaxaceCategoryPage(): void
    {
        $category = ServiceCategory::where('slug', 'relaxace')->first();

        if ($category === null) {
            return;
        }

        $img = fn (string $id, string $name): ?int => $this->media(
            "https://images.unsplash.com/{$id}?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&q=80&w=1080",
            $name,
        );

        $massageCards = [
            ['title' => 'Manuální lymfatické masáže', 'image' => $img('photo-1717500252172-b1840ea64f05', 'relaxace-card-lymfa'), 'url' => '/sluzby/relaxace/lymfaticke-masaze', 'description' => 'Speciální technika lymfatické drenáže podporující imunitu, snižující otoky a napomáhající detoxikaci organismu.'],
            ['title' => 'Klasické masáže', 'image' => $img('photo-1596740926849-2d473dee8d60', 'relaxace-card-klasicke'), 'url' => '/rezervace', 'description' => 'Klasická masáž pro dospělé – uvolnění svalového napětí, regenerace zad, šíje a ramen, podpora správného držení těla.'],
            ['title' => 'Těhotenské masáže', 'image' => $img('photo-1671493235081-5842463637cd', 'relaxace-card-tehotenske'), 'url' => '/sluzby/relaxace/tehotenske-masaze', 'description' => 'Jemné masážní techniky přizpůsobené pro těhotné ženy ve 2. a 3. trimestru. Úleva od bolestí zad a napětí.'],
            ['title' => 'Masáže miminek a dětí', 'image' => $img('photo-1612676244045-b3907a062c59', 'relaxace-card-miminek'), 'url' => '/sluzby/relaxace/masaze-miminek-a-deti', 'description' => 'Masáže posilující pouto mezi rodičem a dítětem. Podporují správný vývoj, zlepšují spánek a pomáhají při kolikách.'],
            ['title' => 'Bylinná napářka', 'image' => $img('photo-1539794830467-1f1755804d13', 'relaxace-card-bylinna'), 'url' => '/sluzby/relaxace/bylinna-naparka', 'description' => 'Tradiční bylinná napářka pro detoxikaci a hlubokou relaxaci. Uvolnění dýchacích cest a regenerace pokožky.'],
        ];

        $category->customPage()->updateOrCreate([], [
            'title' => 'Masáže a relaxace',
            'slug' => 'relaxace-vlastni-stranka',
            'published_at' => now(),
            'content' => [
                $this->brick('hero', [
                    'eyebrow' => 'Relaxace a regenerace',
                    'title' => 'Masáže a relaxace',
                    'features' => '<p>Dopřejte si chvíli pro sebe. Nabízíme širokou nabídku masáží a relaxačních procedur, které vám pomohou uvolnit napětí, zmenšit bolest a obnovit vaši energii.</p>',
                    'image' => $img('photo-1596740926849-2d473dee8d60', 'relaxace-category-hero'),
                    'buttons' => [
                        ['text' => 'Objednat se', 'url' => '/rezervace', 'icon' => 'calendar', 'style' => 'primary'],
                        ['text' => 'Více informací', 'url' => '#sluzby', 'icon' => 'arrow-down', 'style' => 'outline'],
                    ],
                ]),
                $this->brick('cards', [
                    'title' => 'Poskytované služby',
                    'subtitle' => 'Relaxační, těhotenské a lymfatické masáže pro dospělé. Masáže miminek a dětí. Dopřejte si chvíli klidu a profesionální péče o vaše tělo.',
                    'background' => 'white',
                    'columns' => 3,
                    'cards' => array_map(fn (array $c): array => [
                        'image' => $c['image'],
                        'title' => $c['title'],
                        'description' => $c['description'],
                        'text' => 'Více informací',
                        'style' => 'text',
                        'url' => $c['url'],
                    ], $massageCards),
                ]),
                $this->brick('cards', [
                    'title' => 'Relaxace',
                    'subtitle' => 'Bylinná napářka, relaxační rituály s pravým kakaem či červeným vínem a Jin jóga. Dopřejte si regeneraci pro tělo i duši.',
                    'background' => 'alt',
                    'columns' => 3,
                    'cards' => [
                        [
                            'image' => $img('photo-1539794830467-1f1755804d13', 'relaxace-card-bylinna'),
                            'title' => 'Bylinná napářka',
                            'description' => 'Tradiční bylinná napářka pro detoxikaci a hlubokou relaxaci. Uvolnění dýchacích cest a regenerace pokožky.',
                            'text' => 'Více informací',
                            'style' => 'text',
                            'url' => '/sluzby/relaxace/bylinna-naparka',
                        ],
                    ],
                ]),
                $this->brick('cta-banner', [
                    'title' => 'Dopřejte si masáž či relaxační rituál',
                    'subtitle' => 'Rezervujte si termín online a užijte si chvíli klidu a regenerace.',
                    'buttons' => [
                        ['text' => 'Objednat se', 'url' => '/rezervace', 'icon' => 'calendar', 'style' => 'white'],
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

    private function lymphMassagePage(): void
    {
        $service = Service::where('slug', 'lymfaticke-masaze')->first();

        if ($service === null) {
            return;
        }

        $this->customPage($service, 'Manuální lymfatické masáže', [
            $this->brick('hero', [
                'eyebrow' => 'Masáže',
                'title' => 'Manuální lymfatické masáže',
                'features' => '<p>Technika lymfatické drenáže je nadmíru vyhledávanou především u žen a to nejen z kosmetických důvodů, ale i pro svůj zásadní dopad na imunitu, otoky, detoxikaci organismu, únavový syndrom, bolesti hlavy a migrény, stres, celulitidu, přirozenou podporu kojení a podporu hojení jizev.</p>',
                'buttons' => [
                    ['text' => 'Objednat se na masáž', 'url' => '/rezervace', 'icon' => 'calendar', 'style' => 'primary'],
                    ['text' => 'Více informací', 'url' => '#jak-to-funguje', 'icon' => 'arrow-down', 'style' => 'outline'],
                ],
            ]),
            $this->brick('text-list', [
                'eyebrow' => 'Jak to funguje',
                'title' => 'Lymfatický systém a jeho význam',
                'body' => '<p>Jedná se o hmatovou techniku tvořenou souborem kruhovitých nebo spirálovitých hmatů o pomalé frekvenci, jejíž cílem je zlepšit funkci povrchového lymfatického systému, který je uložen v podkoží. Lymfatický (mízní) systém tvoří lymfatické cévy a uzliny, které se z 80 % nachází přímo v podkoží.</p><p>Jako lymfoedém označujeme otok s původem v lymfatickém systému a je definován jako zánětlivé onemocnění kůže a podkoží. Postupně dochází ke stagnaci toku lymfy a pokud není odstraněna příčina, pak může docházet k postupnému zhoršení stavu v postižené oblasti.</p><p>Odvedení přebytečné tekutiny z tkání působí blahodárně na stav lymfatických cest a umožní tak „restartovat“ jejich činnost.</p>',
                'card_style' => 'warning',
                'card_icon' => 'triangle-alert',
                'card_title' => 'Kontraindikace',
                'card_note' => 'Před objednáním se prosím ujistěte, že se na vás nevztahují následující kontraindikace. V případě nejistoty nás neváhejte kontaktovat.',
                'items' => $this->listItems([
                    'Infekční a horečnaté stavy, záněty žil',
                    'Otoky způsobené selháváním srdce nebo ledvin',
                    'Mixedém (hypofunkce štítné žlázy), hemofilie, glaukom',
                    'Akutní zánětlivá onemocnění – masáž vhodná až po odeznění akutní fáze',
                    'Onkologická onemocnění – v některých případech možná po ukončení léčby',
                    'Hypertenze, těžká onemocnění ledvin a jater, angína pectoris',
                    'Rizikové těhotenství',
                ]),
            ]),
            $this->brick('steps', [
                'eyebrow' => 'Jak to probíhá',
                'title' => 'Průběh lymfatické masáže',
                'subtitle' => 'Vstupní vyšetření s diagnostikou a terapií trvá 90 minut. Ideální počet terapií je minimálně 10, optimálně 2× týdně.',
                'steps' => [
                    ['icon' => 'clipboard-list', 'title' => 'Konzultace', 'description' => 'Úvodní rozhovor o vašich potížích, zdravotním stavu a cílech terapie. Společně nastavíme ideální plán ošetření.'],
                    ['icon' => 'search', 'title' => 'Diagnostika', 'description' => 'Vstupní vyšetření stavu lymfatického systému a identifikace problematických oblastí. Trvá cca 90 minut.'],
                    ['icon' => 'hand', 'title' => 'Terapie', 'description' => 'Série jemných masážních tahů pro zlepšení funkce lymfatického systému. Doporučujeme minimálně 10 terapií.'],
                    ['icon' => 'calendar-check', 'title' => 'Plán péče', 'description' => 'Doporučení frekvence návštěv (optimálně 2× týdně) a 1× měsíčně pro udržení výsledků.'],
                ],
            ]),
            $this->brick('feature-cards', [
                'eyebrow' => 'Příznaky a indikace',
                'title' => 'Komu pomůže lymfatická masáž?',
                'columns' => 2,
                'background' => 'alt',
                'cards' => [
                    ['icon' => 'heart-pulse', 'title' => 'Zdravotní indikace', 'description' => '<ul><li>Otoky končetin a obličeje</li><li>Oslabená imunita a časté nemoci</li><li>Bolesti hlavy a migrény</li><li>Celulitida a zadržování tekutin</li><li>Pooperační a poúrazové otoky</li></ul>'],
                    ['icon' => 'sparkles', 'title' => 'Kosmetické přínosy', 'description' => '<ul><li>Detoxikace organismu a čistší pleť</li><li>Redukce celulitidy a zpevnění pokožky</li><li>Podpora hojení jizev</li><li>Zlepšení kvality vlasů a nehtů</li></ul>'],
                ],
                'buttons' => [
                    ['text' => 'Objednat se na masáž', 'url' => '/rezervace', 'icon' => 'calendar', 'style' => 'primary'],
                ],
            ]),
            $this->brick('quote-banner', [
                'text' => 'Zdravá lymfa je základem zdravého těla.',
                'icon' => 'heart',
            ]),
            $this->brick('testimonials', [
                'eyebrow' => 'Co říkají naši klienti',
                'title' => 'Recenze klientů',
                'subtitle' => 'Přečtěte si, jak naše lymfatické masáže pomohly našim klientkám.',
                'background' => 'alt',
                'items' => [
                    ['quote' => 'Po sérii lymfatických masáží se moje otoky výrazně zmenšily a cítím se mnohem lehčeji. Profesionální přístup!', 'author' => 'Jana M.', 'role' => 'Lymfatická masáž'],
                    ['quote' => 'Trápila mě celulitida a zadržování tekutin. Po 10 terapiích vidím úžasné výsledky. Děkuji!', 'author' => 'Petra S.', 'role' => 'Lymfatická masáž'],
                    ['quote' => 'Masáž obličeje mi pomohla s otoky a pleť vypadá mnohem zdravěji. Vřele doporučuji!', 'author' => 'Eva R.', 'role' => 'Lymfatická masáž – obličej'],
                ],
            ]),
            $this->brick('pricing', [
                'eyebrow' => 'Ceník',
                'title' => 'Manuální lymfatické masáže',
                'subtitle' => 'Přehled cen lymfatických masáží.',
                'rows' => [
                    ['name' => 'Vstupní vyšetření', 'note' => '90 min', 'price' => '1 100 Kč'],
                    ['name' => 'Balíček 5 / 10 terapií', 'note' => '60 min', 'price' => '5 000 / 8 500 Kč'],
                    ['name' => 'Balíček 5 / 10 terapií', 'note' => '90 min', 'price' => '7 000 / 12 500 Kč'],
                    ['name' => 'Masáž obličeje, šíje a krku', 'note' => '60 min', 'price' => '1 000 Kč'],
                ],
            ]),
            $this->brick('cta-banner', [
                'title' => 'Zarezervujte si svou masáž',
                'subtitle' => 'Vyberte si z naší nabídky masáží a dopřejte si chvíli péče o své tělo. Rezervujte online jednoduše a rychle.',
                'buttons' => [
                    ['text' => 'Rezervovat masáž', 'url' => '/rezervace', 'icon' => 'calendar', 'style' => 'white'],
                ],
            ]),
        ]);
    }

    private function pregnancyMassagePage(): void
    {
        $service = Service::where('slug', 'tehotenske-masaze')->first();

        if ($service === null) {
            return;
        }

        $this->customPage($service, 'Těhotenské masáže', [
            $this->brick('hero', [
                'eyebrow' => 'Masáže',
                'title' => 'Těhotenské masáže',
                'features' => '<p>Těhotenská masáž je soubor jemných hmatů, které si budoucí maminka užívá v leže na boku a pokud bříško dovolí, tak také na zádech. Masáž ženu zrelaxuje a pomůže jí napojit se na své miminko.</p>',
                'buttons' => [
                    ['text' => 'Objednat se na masáž', 'url' => '/rezervace', 'icon' => 'calendar', 'style' => 'primary'],
                    ['text' => 'Více informací', 'url' => '#jak-to-funguje', 'icon' => 'arrow-down', 'style' => 'outline'],
                ],
            ]),
            $this->brick('text-list', [
                'eyebrow' => 'Jak to funguje',
                'title' => 'Masáže pro budoucí maminky',
                'body' => '<p>Masáž ženu zrelaxuje jak po fyzické tak psychické stránce. Žena má možnost povolit, zklidnit se, odpočinout si od uhoněného světa a napojit se na své miminko. Budoucí maminka během masáže povolí svalové napětí, což je nedílnou součástí porodu jako takového.</p><p><strong>Masáže jsou vhodné pro ženy v II. a III. trimestru.</strong></p>',
                'card_style' => 'warning',
                'card_icon' => 'triangle-alert',
                'card_title' => 'Kontraindikace',
                'card_note' => 'Těhotenská masáž není vhodná v následujících případech. V případě nejistoty nás neváhejte kontaktovat.',
                'items' => $this->listItems([
                    'Rizikové těhotenství, opakované potraty',
                    'Nevolnost, průjem, akutní onemocnění nebo poranění',
                    'Horečnaté a zánětlivé stavy',
                    'Křečové žíly a podobná onemocnění',
                    'Onkologické onemocnění',
                ]),
            ]),
            $this->brick('steps', [
                'eyebrow' => 'Jak to probíhá',
                'title' => 'Průběh těhotenské masáže',
                'subtitle' => 'Od první konzultace až po hlubokou relaxaci.',
                'steps' => [
                    ['icon' => 'clipboard-list', 'title' => 'Konzultace', 'description' => 'Rozhovor o průběhu těhotenství, obtížích a přáních.'],
                    ['icon' => 'bed', 'title' => 'Příprava', 'description' => 'Pohodlné uložení na bok s podložkami pro maximální komfort.'],
                    ['icon' => 'hand', 'title' => 'Masáž', 'description' => 'Jemné masážní tahy přizpůsobené trimestru a potřebám.'],
                    ['icon' => 'heart', 'title' => 'Relaxace', 'description' => 'Chvíle klidu pro napojení se na miminko.'],
                ],
            ]),
            $this->brick('feature-cards', [
                'eyebrow' => 'Příznaky a indikace',
                'title' => 'Komu pomůže těhotenská masáž?',
                'columns' => 2,
                'background' => 'alt',
                'cards' => [
                    ['icon' => 'baby', 'title' => 'Během těhotenství', 'description' => '<ul><li>Bolesti zad a bederní páteře</li><li>Otoky nohou a kotníků</li><li>Napětí v ramenou a šíji</li><li>Nespavost a úzkost</li><li>Celková fyzická únava</li></ul>'],
                    ['icon' => 'sun', 'title' => 'Přínosy masáže', 'description' => '<ul><li>Úleva od bolestí a napětí</li><li>Zlepšení krevního oběhu</li><li>Snížení stresu a úzkosti</li><li>Lepší spánek a relaxace</li></ul>'],
                ],
                'buttons' => [
                    ['text' => 'Objednat se na masáž', 'url' => '/rezervace', 'icon' => 'calendar', 'style' => 'primary'],
                ],
            ]),
            $this->brick('quote-banner', [
                'text' => 'Pohodová uvolněná maminka = pohodové a relaxované miminko.',
                'icon' => 'heart',
            ]),
            $this->brick('testimonials', [
                'eyebrow' => 'Co říkají naši klienti',
                'title' => 'Recenze klientů',
                'subtitle' => 'Přečtěte si, jak naše těhotenské masáže pomohly budoucím maminkám.',
                'background' => 'alt',
                'items' => [
                    ['quote' => 'Ve třetím trimestru mě trápily hrozné bolesti zad. Po masáži jsem se cítila jako znovuzrozená. Úžasný zážitek!', 'author' => 'Karolína D.', 'role' => 'Těhotenská masáž'],
                    ['quote' => 'Masáž mi pomohla uvolnit napětí a lépe spát. Navíc jsem si užila krásný čas jen pro sebe před příchodem miminka.', 'author' => 'Monika P.', 'role' => 'Těhotenská masáž'],
                    ['quote' => 'Profesionální přístup a příjemné prostředí. Cítila jsem se bezpečně a skvěle relaxovala. Doporučuji každé mamince!', 'author' => 'Simona H.', 'role' => 'Těhotenská masáž'],
                ],
            ]),
            $this->brick('pricing', [
                'eyebrow' => 'Ceník',
                'title' => 'Těhotenské masáže',
                'subtitle' => 'Přehled cen těhotenských masáží.',
                'rows' => [
                    ['name' => 'Těhotenská masáž', 'note' => '60 min', 'price' => '1 000 Kč'],
                    ['name' => 'Těhotenská masáž', 'note' => '90 min', 'price' => '1 400 Kč'],
                ],
            ]),
            $this->brick('cta-banner', [
                'title' => 'Zarezervujte si svou masáž',
                'subtitle' => 'Vyberte si z naší nabídky masáží a dopřejte si chvíli péče o své tělo. Rezervujte online jednoduše a rychle.',
                'buttons' => [
                    ['text' => 'Rezervovat masáž', 'url' => '/rezervace', 'icon' => 'calendar', 'style' => 'white'],
                ],
            ]),
        ]);
    }

    private function babyMassagePage(): void
    {
        $service = Service::where('slug', 'masaze-miminek-a-deti')->first();

        if ($service === null) {
            return;
        }

        $this->customPage($service, 'Masáže miminek a dětí', [
            $this->brick('hero', [
                'eyebrow' => 'Masáže',
                'title' => 'Masáže miminek a dětí',
                'features' => '<p>Dotek je první řečí lásky, kterou miminko vnímá ještě před narozením. Masáže miminek a dětí vycházejí z tisícileté tradice mnoha kultur světa a jsou jedním z nejkrásnějších způsobů, jak posilovat pouto mezi rodičem a dítětem.</p>',
                'buttons' => [
                    ['text' => 'Mám zájem o masáž', 'url' => '/rezervace', 'icon' => 'heart', 'style' => 'primary'],
                    ['text' => 'Více informací', 'url' => '#jak-to-probiha', 'icon' => 'arrow-down', 'style' => 'outline'],
                ],
            ]),
            $this->brick('steps', [
                'eyebrow' => 'Jak to probíhá',
                'title' => 'Průběh masáže miminek',
                'subtitle' => 'Od prvního setkání až po samostatnou masáž doma.',
                'steps' => [
                    ['icon' => 'clipboard-list', 'title' => 'Úvod', 'description' => 'Seznámení s masážními technikami, vhodnými oleji a signály miminka.'],
                    ['icon' => 'baby', 'title' => 'Příprava', 'description' => 'Pohodlné uložení miminka v teplém prostředí.'],
                    ['icon' => 'hand', 'title' => 'Masáž', 'description' => 'Krok za krokem vás provedu masážními tahy od nožiček po obličej.'],
                    ['icon' => 'house', 'title' => 'Doma', 'description' => 'Naučenou techniku můžete opakovat doma kdykoliv budete chtít.'],
                ],
            ]),
            $this->brick('feature-cards', [
                'eyebrow' => 'Příznaky a indikace',
                'title' => 'Komu pomůže masáž miminek?',
                'columns' => 2,
                'background' => 'alt',
                'cards' => [
                    ['icon' => 'heart-pulse', 'title' => 'Pro miminka', 'description' => '<ul><li>Zmírnění kolik a nadýmání</li><li>Podpora trávení a lepšího spánku</li><li>Posílení imunitního systému</li><li>Uvolnění svalového napětí</li><li>Podpora motorického vývoje</li></ul>'],
                    ['icon' => 'sparkles', 'title' => 'Pro rodiče', 'description' => '<ul><li>Posilování vazby mezi rodičem a dítětem</li><li>Snížení stresu a pláče</li><li>Radost ze společné aktivity</li><li>Jistota v péči o miminko</li></ul>'],
                ],
                'buttons' => [
                    ['text' => 'Objednat se na masáž', 'url' => '/rezervace', 'icon' => 'calendar', 'style' => 'primary'],
                ],
            ]),
            $this->brick('quote-banner', [
                'text' => 'Dotek je první řečí lásky.',
                'icon' => 'heart',
            ]),
            $this->brick('testimonials', [
                'eyebrow' => 'Co říkají naši klienti',
                'title' => 'Recenze klientů',
                'subtitle' => 'Přečtěte si, jak masáže miminek pomohly našim rodičům a jejich dětem.',
                'background' => 'alt',
                'items' => [
                    ['quote' => 'Dcerka měla velké koliky a masáže nám úžasně pomohly. Naučila jsem se techniku a dělám ji každý večer. Spí mnohem lépe!', 'author' => 'Lenka T.', 'role' => 'Masáž miminka'],
                    ['quote' => 'Krásný zážitek pro mě i synka. Lekce byla v příjemném prostředí a lektorka nás skvěle provedla. Vřele doporučuji!', 'author' => 'Markéta V.', 'role' => 'Masáž miminka'],
                    ['quote' => 'Absolvovali jsme kurz 4 lekcí a masáž se stala naším krásným večerním rituálem. Dcera se vždy uklidní a usne spokojená.', 'author' => 'Anna K.', 'role' => 'Kurz masáží'],
                ],
            ]),
            $this->brick('pricing', [
                'eyebrow' => 'Ceník',
                'title' => 'Masáže miminek a dětí',
                'subtitle' => 'Přehled cen masáží pro miminka a děti.',
                'rows' => [
                    ['name' => 'Masáž miminek a dětí do 5 let – první návštěva', 'note' => '30 min', 'price' => '500 Kč'],
                    ['name' => 'Masáž miminek a dětí do 5 let – první návštěva', 'note' => '45 min', 'price' => '750 Kč'],
                    ['name' => 'Masáž miminek a dětí do 5 let – další návštěva', 'note' => '30 min', 'price' => '500 Kč'],
                    ['name' => 'Masáž dětí 6–10 let', 'note' => '45 min', 'price' => '700 Kč'],
                    ['name' => 'Masáž dětí 11–15 let', 'note' => '60 min', 'price' => '900 Kč'],
                    ['name' => 'Kurz 4 návštěv pro rodiče s kojenci', 'note' => '4×', 'price' => '1 800 Kč'],
                ],
            ]),
            $this->brick('cta-banner', [
                'title' => 'Zarezervujte si svou masáž',
                'subtitle' => 'Vyberte si z naší nabídky masáží a dopřejte si chvíli péče o své tělo. Rezervujte online jednoduše a rychle.',
                'buttons' => [
                    ['text' => 'Rezervovat masáž', 'url' => '/rezervace', 'icon' => 'calendar', 'style' => 'white'],
                ],
            ]),
        ]);
    }

    private function herbalSteamPage(): void
    {
        $service = Service::where('slug', 'bylinna-naparka')->first();

        if ($service === null) {
            return;
        }

        $this->customPage($service, 'Bylinná napářka', [
            $this->brick('hero', [
                'eyebrow' => 'Relaxace',
                'title' => 'Bylinná napářka',
                'features' => '<p><strong>Soukromý rituál pro ženy</strong></p><p>Bylinná napářka je procedura, která poskytuje holistickou péči o ženské zdraví. Díky působení teplých bylinných výparů dochází k prohřátí ženských pohlavních orgánů, lepší krevní cirkulaci v oblasti malé pánve a k absorpci účinných látek z bylin krevním oběhem.</p>',
                'buttons' => [
                    ['text' => 'Zavolat: +420 604 791 215', 'url' => 'tel:+420604791215', 'icon' => 'phone', 'style' => 'primary'],
                    ['text' => 'Více informací', 'url' => '#o-naparce', 'icon' => 'arrow-down', 'style' => 'outline'],
                ],
            ]),
            $this->brick('text-list', [
                'eyebrow' => 'O napářce',
                'title' => 'Tisíciletá tradice ženské péče',
                'body' => '<p>Kromě blahodárných účinků na tělo umožňuje hlubokou relaxaci ženy, která přispívá k její pohodě a napomáhá tím k jejímu duševnímu zdraví. Umožňuje ženám zastavit se po uspěchaném dni a obrátit pozornost směrem k sobě.</p><p>Ženy po celém světě využívají účinků teplé páry v rámci své pravidelné očisty, nebo jako podpůrné léčby při gynekologických potížích. Obrovský benefit má v období posledních týdnů před porodem, u porodu a v šestinedělí.</p><p><em>U kontraindikací je třeba rozlišit, zda-li se jedná o kontraindikaci absolutní či relativní. Proto se na nás neváhejte obrátit.</em></p>',
                'card_style' => 'warning',
                'card_icon' => 'triangle-alert',
                'card_title' => 'Kontraindikace',
                'card_note' => 'Napářka není vhodná v následujících případech. V případě nejistoty nás neváhejte kontaktovat.',
                'items' => $this->listItems([
                    'Vaginální krvácení',
                    'Těhotenství do ukončeného 38. tt',
                    'Zamlklé těhotenství a potrat',
                    'Po ovulaci při plánovaném rodičovství',
                    'Akutní kvasinkový a bakteriální zánět',
                    'Šestinedělí po císařském řezu',
                    'Hormonální antikoncepční implantát',
                    'Horečka',
                    'Vulvovaginální varixy a hemeroidy',
                    'Výhřez dělohy nebo sestup pánevních orgánů',
                ]),
            ]),
            $this->brick('feature-cards', [
                'eyebrow' => 'Přínosy napářky',
                'title' => 'Kdy a pro koho je napářka vhodná?',
                'columns' => 2,
                'background' => 'white',
                'cards' => [
                    ['icon' => 'heart-pulse', 'title' => 'Ženské zdraví a cyklus', 'description' => '<ul><li>Podporuje plodnost ženy</li><li>Potlačuje známky PMS</li><li>Upravuje menstruační cyklus</li><li>Ulevuje od příznaků endometriózy</li><li>Odstraní sraženiny z menstruační krve</li><li>Zmenšuje děložní myomy</li><li>Podporuje samočistící funkce pochvy</li><li>Odstraňuje bakteriální infekci a zvýšený sekret</li></ul>'],
                    ['icon' => 'sparkles', 'title' => 'Tělo, mateřství a relaxace', 'description' => '<ul><li>Pomáhá připravit porodní cesty od 38. tt</li><li>Pomáhá čistit ženu po porodu</li><li>Změkčuje jizvy a urychluje jejich hojení</li><li>Zvlhčení vagíny (zvláště u žen po přechodu)</li><li>Zvyšuje libido a sexuální senzitivitu</li><li>Prohřívá a uvolňuje svalové napětí</li><li>Napomáhá hojení hemeroidů</li><li>Podporuje detoxikaci a emoční rovnováhu</li></ul>'],
                ],
                'buttons' => [
                    ['text' => 'Objednat telefonicky', 'url' => 'tel:+420604791215', 'icon' => 'phone', 'style' => 'primary'],
                ],
            ]),
            $this->brick('steps', [
                'eyebrow' => 'Průběh procedury',
                'title' => 'Jak taková napářka probíhá?',
                'subtitle' => 'Celý rituál je navržen tak, abyste se cítila bezpečně, v teple a pohodlí.',
                'steps' => [
                    ['icon' => 'leaf', 'title' => 'Výběr bylinek', 'description' => 'Žena si rozvoní vhodné bylinky, které poté se záměrem vložíme do lněného pytlíčku a zavaříme v hrnci pod pokličkou.'],
                    ['icon' => 'sparkles', 'title' => 'Příprava prostředí', 'description' => 'Zajistíme ženě tepelný komfort a pohodlí za doprovodu relaxační hudby, svíček a léčivé zvonkohry Koshi.'],
                    ['icon' => 'flame', 'title' => 'Napařování', 'description' => 'Připravený bylinný odvar umístíme pod napařovací židličku. Máme pro vás připravenou dlouhou sukni, aby se pára neztrácela.'],
                    ['icon' => 'moon', 'title' => 'Odpočinek', 'description' => 'Po bylinné napářce následuje odpočinek nebo se můžete nechat hýčkat relaxační masáží.'],
                ],
            ]),
            $this->brick('quote-banner', [
                'text' => 'Buďme k sobě laskavé a dopřejme si čas na napářku.',
                'icon' => 'heart',
            ]),
            $this->brick('text-list', [
                'icon' => 'flame',
                'title' => 'Jemné napařování',
                'body' => '<p>Pokud se s napářkou ženy seznamují a chtějí vyzkoušet své první sezení, začínáme s jemným 10min. napařováním. Při další proceduře se již může přejít k pokročilému napařování, které trvá cca 30 min.</p><p><em>Délka procedury vychází z citlivosti každé konkrétní ženy. Někdy je méně a častěji VÍCE.</em></p>',
                'card_style' => 'soft',
                'card_note' => 'Jemné desetiminutové napařování se hodí pro ženy, které:',
                'items' => $this->listItems([
                    'Jsou mladší 13 let',
                    'Mají krátké menstruační cykly (27 dní a méně)',
                    'Mívají samovolné krvácení mimo cyklus',
                    'Jsou náchylné na vaginální infekce',
                    'Trpí na noční pocení nebo návaly horka',
                    'Mají implantováno nitroděložní tělísko',
                    'Absolvovaly gynekologickou operaci méně než 6 týdnů zpětně',
                ]),
            ]),
            $this->brick('pricing', [
                'eyebrow' => 'Ceník',
                'title' => 'Nabízíme tři varianty napářky',
                'subtitle' => 'Vaginální napářku si můžete dopřát u nás nebo přímo u vás doma.',
                'rows' => [
                    ['name' => 'Napářka s relaxací', 'note' => 'cca 60 minut', 'price' => '1 200 Kč'],
                    ['name' => 'Napářka + masáž 60 min', 'note' => 'cca 90 minut', 'price' => '2 400 Kč'],
                    ['name' => 'Napářka + masáž 90 min', 'note' => 'cca 120 minut', 'price' => '2 900 Kč'],
                ],
                'note' => 'V rámci všech tří variant vám rádi nabídneme bylinný čaj, nebo sklenici červeného vína či hrnek pravého kakaa. V ceně jsou také bylinky a veškeré vybavení k proceduře. Ženám, které by chtěly napářku absolvovat u nás, doporučujeme vzít si s sebou teplé ponožky a zajistit si odvoz či doprovod domů.',
            ]),
            $this->brick('cta-banner', [
                'title' => 'Máte zájem o bylinnou napářku?',
                'subtitle' => 'Tuto službu je možné objednat pouze telefonicky. Zavolejte nám a rádi vám nalezneme vhodný termín. Jsme tu pro vás Po–Pá 8:00–17:00.',
                'buttons' => [
                    ['text' => 'Zavolat: +420 604 791 215', 'url' => 'tel:+420604791215', 'icon' => 'phone', 'style' => 'white'],
                ],
            ]),
        ]);
    }

    private function pristrojovaTerapieCategoryPage(): void
    {
        $category = ServiceCategory::where('slug', 'pristrojova-terapie')->first();

        if ($category === null) {
            return;
        }

        $img = fn (string $id, string $name): ?int => $this->media(
            "https://images.unsplash.com/{$id}?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&q=80&w=1080",
            $name,
        );

        $category->customPage()->updateOrCreate([], [
            'title' => 'Přístrojová terapie',
            'slug' => 'pristrojova-terapie-vlastni-stranka',
            'published_at' => now(),
            'content' => [
                $this->brick('hero', [
                    'eyebrow' => 'Moderní technologie',
                    'title' => 'Přístrojová terapie',
                    'features' => '<p>Přístrojová terapie využívá moderní technologie k urychlení hojení, úlevě od bolesti a redukci otoků. Doplňuje manuální terapii a pomáhá vám rychleji se vrátit zpět do pohody.</p>',
                    'image' => $img('photo-1576770075856-86b01944b92b', 'pristrojova-category-hero'),
                    'buttons' => [
                        ['text' => 'Objednat se', 'url' => '/rezervace', 'icon' => 'calendar', 'style' => 'primary'],
                        ['text' => 'Více informací', 'url' => '#sluzby', 'icon' => 'arrow-down', 'style' => 'outline'],
                    ],
                ]),
                $this->brick('feature-cards', [
                    'eyebrow' => 'Naše služby',
                    'title' => 'Co nabízíme',
                    'subtitle' => 'Přístrojová terapie u nás zahrnuje laserovou terapii a lokální kryoterapii pro rychlejší úlevu a regeneraci.',
                    'background' => 'white',
                    'columns' => 2,
                    'cards' => [
                        [
                            'icon' => 'sun',
                            'title' => 'Laserová terapie',
                            'description' => '<p>Vysokovýkonný laser pro hlubší prohřátí tkání, urychlení hojení a úlevu od bolesti pohybového aparátu.</p>',
                            'text' => 'Více informací',
                            'style' => 'text',
                            'url' => '/sluzby/pristrojova-terapie/laserova-terapie',
                        ],
                        [
                            'icon' => 'snowflake',
                            'title' => 'Kryoterapie',
                            'description' => '<p>Lokální aplikace chladu pro rychlou úlevu od bolesti, snížení zánětu a otoku po akutním zranění.</p>',
                            'text' => 'Více informací',
                            'style' => 'text',
                            'url' => '/sluzby/pristrojova-terapie/kryoterapie',
                        ],
                    ],
                ]),
                $this->brick('steps', [
                    'eyebrow' => 'Jak to probíhá',
                    'title' => 'Cesta k přístrojové terapii',
                    'subtitle' => 'Od konzultace až po viditelné výsledky vás provedeme celým procesem.',
                    'steps' => [
                        ['icon' => 'message-circle', 'title' => 'Konzultace', 'description' => 'Probereme vaše potíže a doporučíme vhodnou terapii.'],
                        ['icon' => 'search', 'title' => 'Vstupní měření', 'description' => 'Zhodnotíme váš stav a nastavíme parametry přístroje na míru.'],
                        ['icon' => 'zap', 'title' => 'Terapie', 'description' => 'Samotná aplikace laseru nebo kryoterapie je rychlá a bezbolestná.'],
                        ['icon' => 'check-circle', 'title' => 'Výsledky', 'description' => 'Sledujeme úlevu a v případě potřeby terapii zopakujeme.'],
                    ],
                ]),
                $this->brick('pricing', [
                    'eyebrow' => 'Ceník',
                    'title' => 'Ceník přístrojové terapie',
                    'subtitle' => 'Přístrojovou terapii je možné objednat telefonicky nebo využít jako doplněk k manuální terapii.',
                    'rows' => [
                        ['name' => 'Laserová terapie', 'note' => '30 min', 'price' => '500 Kč'],
                        ['name' => 'Kryoterapie', 'note' => '15 min', 'price' => '490 Kč'],
                    ],
                    'buttons' => [
                        ['text' => 'Objednat se', 'url' => '/rezervace', 'icon' => 'calendar', 'style' => 'primary'],
                    ],
                ]),
                $this->brick('testimonials', [
                    'eyebrow' => 'Co říkají naši klienti',
                    'title' => 'Recenze klientů',
                    'subtitle' => 'Přečtěte si, jak přístrojová terapie pomohla našim klientům.',
                    'background' => 'alt',
                    'items' => [
                        ['quote' => 'Po zranění kotníku mi laser výrazně urychlil hojení. Otok ustoupil během pár dní. Rozhodně doporučuji!', 'author' => 'Tomáš R.', 'role' => 'Laserová terapie'],
                        ['quote' => 'Kryoterapie mi skvěle pomohla od akutní bolesti zad. Rychlá úleva bez léků.', 'author' => 'Veronika K.', 'role' => 'Kryoterapie'],
                        ['quote' => 'Kombinace laseru a manuální terapie mi pomohla vrátit se rychleji ke sportu. Skvělý přístup.', 'author' => 'Martin P.', 'role' => 'Přístrojová terapie'],
                    ],
                ]),
                $this->brick('cta-banner', [
                    'title' => 'Máte zájem o přístrojovou terapii?',
                    'subtitle' => 'Objednejte se a využijte moderní technologie pro rychlejší úlevu a regeneraci.',
                    'buttons' => [
                        ['text' => 'Objednat se', 'url' => '/rezervace', 'icon' => 'calendar', 'style' => 'white'],
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

    private function cryotherapyPage(): void
    {
        $service = Service::where('slug', 'kryoterapie')->first();

        if ($service === null) {
            return;
        }

        $img = fn (string $id, string $name): ?int => $this->media(
            "https://images.unsplash.com/{$id}?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&q=80&w=1080",
            $name,
        );

        $this->customPage($service, 'Lokální kryoterapie', [
            $this->brick('hero', [
                'eyebrow' => 'Přístrojová terapie',
                'title' => 'Lokální kryoterapie',
                'features' => '<p>Lokální kryoterapie využívá cílenou aplikaci chladu k rychlé úlevě od bolesti, snížení zánětu a otoku. Je ideální po akutním zranění i při přetížení pohybového aparátu.</p>',
                'buttons' => [
                    ['text' => 'Zavolat: +420 604 791 215', 'url' => 'tel:+420604791215', 'icon' => 'phone', 'style' => 'primary'],
                    ['text' => 'Více informací', 'url' => '#o-kryoterapii', 'icon' => 'arrow-down', 'style' => 'outline'],
                ],
            ]),
            $this->brick('photo-text', [
                'image' => $img('photo-1571019613454-1cb2f99b2d8b', 'kryoterapie-about'),
                'image_position' => 'left',
                'title' => 'O lokální kryoterapii',
                'body' => '<p>Lokální kryoterapie je metoda, při které se na postižené místo aplikuje proud velmi chladného vzduchu. Krátkodobé prudké ochlazení tkáně vyvolá nejprve stažení a následně rozšíření cév, čímž se výrazně zlepší prokrvení a látková výměna v ošetřované oblasti.</p><p>Aplikace je rychlá, bezbolestná a bez vedlejších účinků. Používá se samostatně pro rychlou úlevu od bolesti nebo jako doplněk manuální terapie pro urychlení hojení.</p>',
            ]),
            $this->brick('feature-cards', [
                'eyebrow' => 'Indikace',
                'title' => 'Kdy je kryoterapie vhodná?',
                'columns' => 4,
                'background' => 'alt',
                'cards' => [
                    ['icon' => 'activity', 'title' => 'Bolesti svalů', 'description' => '<p>Úleva od bolesti a napětí svalů po zátěži nebo přetížení.</p>'],
                    ['icon' => 'flame', 'title' => 'Záněty šlach a kloubů', 'description' => '<p>Snížení zánětu u tendinitid, entezopatií a bolestivých kloubů.</p>'],
                    ['icon' => 'bandage', 'title' => 'Akutní zranění', 'description' => '<p>Rychlé ošetření podvrtnutí, naražení a otoků po úrazu.</p>'],
                    ['icon' => 'bone', 'title' => 'Bolesti páteře', 'description' => '<p>Úleva od akutních bolestí zad a krční páteře.</p>'],
                ],
            ]),
            $this->brick('quote-banner', [
                'text' => 'Chlad, který uleví od bolesti a nastartuje hojení.',
                'icon' => 'snowflake',
            ]),
            $this->brick('testimonials', [
                'eyebrow' => 'Co říkají naši klienti',
                'title' => 'Recenze klientů',
                'subtitle' => 'Přečtěte si, jak lokální kryoterapie pomohla našim klientům.',
                'background' => 'alt',
                'items' => [
                    ['quote' => 'Po naražení ramene mi kryoterapie během chvíle ulevila od bolesti a otok rychle ustoupil. Skvělé!', 'author' => 'Jakub M.', 'role' => 'Kryoterapie'],
                    ['quote' => 'Trápila mě akutní bolest zad. Po několika aplikacích chladu jsem se cítila mnohem lépe. Doporučuji.', 'author' => 'Lucie H.', 'role' => 'Kryoterapie'],
                    ['quote' => 'Jako sportovec využívám kryoterapii po náročných trénincích. Regenerace je znatelně rychlejší.', 'author' => 'Ondřej K.', 'role' => 'Kryoterapie'],
                ],
            ]),
            $this->brick('callout', [
                'icon' => 'ban',
                'title' => 'Kdy kryoterapii nepoužíváme',
                'body' => '<p>Kryoterapie není vhodná při přecitlivělosti na chlad, poruchách prokrvení, otevřených ranách v místě aplikace a některých onemocněních cév. V případě nejistoty nás neváhejte kontaktovat.</p>',
                'note' => 'Objednávky přijímáme telefonicky na čísle +420 604 791 215.',
                'buttons' => [
                    ['text' => 'Objednat se telefonicky', 'url' => 'tel:+420604791215', 'icon' => 'phone', 'style' => 'soft'],
                ],
            ]),
            $this->brick('cta-banner', [
                'title' => 'Máte zájem o kryoterapii?',
                'subtitle' => 'Tuto službu je možné objednat telefonicky. Zavolejte nám a rádi vám nalezneme vhodný termín.',
                'buttons' => [
                    ['text' => 'Zavolat: +420 604 791 215', 'url' => 'tel:+420604791215', 'icon' => 'phone', 'style' => 'white'],
                ],
            ]),
        ]);
    }

    private function laserPage(): void
    {
        $service = Service::where('slug', 'laserova-terapie')->first();

        if ($service === null) {
            return;
        }

        $img = fn (string $id, string $name): ?int => $this->media(
            "https://images.unsplash.com/{$id}?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&q=80&w=1080",
            $name,
        );

        $this->customPage($service, 'Laserová terapie', [
            $this->brick('hero', [
                'eyebrow' => 'Přístrojová terapie',
                'title' => 'Laserová terapie',
                'features' => '<p>Vysokovýkonná laserová terapie využívá soustředěné světlo k hlubšímu prohřátí tkání. Stimuluje látkovou výměnu buněk, tlumí bolest i zánět a výrazně urychluje hojení.</p>',
                'buttons' => [
                    ['text' => 'Zavolat: +420 604 791 215', 'url' => 'tel:+420604791215', 'icon' => 'phone', 'style' => 'primary'],
                    ['text' => 'Více informací', 'url' => '#o-laseroterapii', 'icon' => 'arrow-down', 'style' => 'outline'],
                ],
            ]),
            $this->brick('photo-text', [
                'image' => $img('photo-1591343395082-e120087004b4', 'laser-about'),
                'image_position' => 'left',
                'title' => 'O laseroterapii',
                'body' => '<p>Vysokovýkonný laser proniká do hlubších vrstev tkáně, kde stimuluje buněčný metabolismus a podporuje regeneraci. Díky tomu urychluje hojení, tlumí bolest a snižuje zánět.</p><p>Aplikace je příjemná a bezbolestná – klient vnímá pouze mírné teplo. Laserová terapie se používá samostatně i jako doplněk manuální terapie a rehabilitace.</p>',
            ]),
            $this->brick('feature-cards', [
                'eyebrow' => 'Indikace',
                'title' => 'Pro koho je laser vhodný?',
                'columns' => 2,
                'background' => 'white',
                'cards' => [
                    ['icon' => 'activity', 'title' => 'Bolesti pohybového aparátu', 'description' => '<ul><li>Bolesti zad, kloubů a šíje</li><li>Sportovní zranění a přetížení</li><li>Svalové a šlachové obtíže</li><li>Artróza a degenerativní změny</li><li>Pooperační a poúrazové stavy</li></ul>'],
                    ['icon' => 'plus', 'title' => 'Další indikace', 'description' => '<ul><li>Hojení ran a jizev</li><li>Záněty šlach a burz</li><li>Otoky a hematomy</li><li>Neuralgie a nervové obtíže</li><li>Chronické bolestivé stavy</li></ul>'],
                ],
                'buttons' => [
                    ['text' => 'Objednat telefonicky', 'url' => 'tel:+420604791215', 'icon' => 'phone', 'style' => 'primary'],
                ],
            ]),
            $this->brick('quote-banner', [
                'text' => 'Světlo, které urychluje hojení a uleví od bolesti.',
                'icon' => 'sun',
            ]),
            $this->brick('testimonials', [
                'eyebrow' => 'Co říkají naši klienti',
                'title' => 'Recenze klientů',
                'subtitle' => 'Přečtěte si, jak laserová terapie pomohla našim klientům.',
                'background' => 'alt',
                'items' => [
                    ['quote' => 'Laser mi pomohl s chronickým zánětem šlachy, se kterým jsem se trápil měsíce. Konečně bez bolesti!', 'author' => 'Petr S.', 'role' => 'Laserová terapie'],
                    ['quote' => 'Po operaci kolena mi laserová terapie urychlila hojení jizvy a návrat k pohybu. Děkuji!', 'author' => 'Alena V.', 'role' => 'Laserová terapie'],
                    ['quote' => 'Výborná úleva od bolesti zad. Aplikace je příjemná a výsledky se dostavily rychle.', 'author' => 'David N.', 'role' => 'Laserová terapie'],
                ],
            ]),
            $this->brick('callout', [
                'icon' => 'zap',
                'title' => 'Kdy kombinujeme laser s kryoterapií',
                'body' => '<p>U akutních stavů s otokem často začínáme kryoterapií pro rychlé zklidnění a následně přidáme laser pro urychlení hojení. U chronických obtíží naopak volíme laser samostatně. Nejvhodnější kombinaci vám doporučíme na konzultaci.</p>',
                'note' => 'Objednávky přijímáme telefonicky na čísle +420 604 791 215.',
                'buttons' => [
                    ['text' => 'Objednat se telefonicky', 'url' => 'tel:+420604791215', 'icon' => 'phone', 'style' => 'soft'],
                ],
            ]),
            $this->brick('cta-banner', [
                'title' => 'Máte zájem o laseroterapii?',
                'subtitle' => 'Tuto službu je možné objednat telefonicky. Zavolejte nám a rádi vám nalezneme vhodný termín.',
                'buttons' => [
                    ['text' => 'Zavolat: +420 604 791 215', 'url' => 'tel:+420604791215', 'icon' => 'phone', 'style' => 'white'],
                ],
            ]),
        ]);
    }

    /**
     * Wrap a flat list of strings into the repeater item shape ({text: ...}) used
     * by the text-list brick, so the seeded content stays editable in Filament.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array{text: string}>
     */
    private function listItems(array $texts): array
    {
        return array_map(fn (string $text): array => ['text' => $text], $texts);
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
