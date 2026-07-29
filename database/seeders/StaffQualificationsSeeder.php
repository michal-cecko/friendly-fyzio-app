<?php

namespace Database\Seeders;

use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Fills in the team's `education` and `certifications` — the "Vzdělání" and
 * "Certifikované kurzy" blocks published on friendlyfyzio.cz/nas-tym — which
 * {@see RealDataSeeder} never seeded, leaving the qualifications section of
 * every /o-nas/{slug} detail page empty.
 *
 * ---------------------------------------------------------------------------
 * Owner decisions encoded below — not derivable from the data, do not
 * "simplify".
 * ---------------------------------------------------------------------------
 *
 *  - **Its own seeder, not a RealDataSeeder addition.** Production has diverged
 *    through manual edits in the panel, so RealDataSeeder can no longer be
 *    re-run there. This one touches nothing but the two qualification arrays.
 *  - **Fill-when-empty by default.** A profile that already carries
 *    qualifications is left alone, so a colleague's hand-typed course is never
 *    silently discarded. `staff:sync-qualifications --overwrite` re-syncs
 *    everyone from the data below when the website is updated again.
 *  - **The website is not crawled.** The data was transcribed once, verbatim,
 *    and lives here — the seeder makes no HTTP request and needs no network.
 *  - **Sections with no column are folded into the two arrays**: "Stáže",
 *    "Praxe" and postgraduate study join `education` (institution + period
 *    shaped); "Semináře" and "Konference" join `certifications` (name + year).
 *  - Only the six physiotherapists are listed. Michaela Hrubá, Denisa Nováková,
 *    Kristýna Černá and Adéla Macurová publish nothing beyond a job title, and
 *    Lucie Amani / Jakub Trepáč are external room-renters who are not staff at
 *    all ({@see RealDataSeeder::pruneExternalRentalStaff()}).
 *
 * Idempotent: the same input yields the same rows, and a second plain run is a
 * no-op because every profile is non-empty by then. People are matched on the
 * login e-mail, falling back to the name — a person who matches neither is
 * reported, never created.
 *
 * Run via `artisan db:seed --class=StaffQualificationsSeeder`, or through
 * `artisan staff:sync-qualifications` for the --overwrite / --dry-run flags.
 */
class StaffQualificationsSeeder extends Seeder
{
    public function run(): void
    {
        $this->sync();
    }

    /**
     * Writes the qualifications onto the matching staff profiles.
     *
     * @return array{updated: int, skipped: int, missing: list<string>}
     */
    public function sync(bool $overwrite = false, bool $dryRun = false): array
    {
        $updated = 0;
        $skipped = 0;
        $missing = [];

        foreach ($this->qualifications() as $entry) {
            $profile = $this->resolveProfile($entry['email'], $entry['name']);

            if (! $profile instanceof StaffProfile) {
                $missing[] = "{$entry['name']} <{$entry['email']}>";

                continue;
            }

            $changed = false;

            foreach (['education', 'certifications'] as $field) {
                if (! isset($entry[$field])) {
                    continue;
                }

                if (! $overwrite && ! empty($profile->{$field})) {
                    $skipped++;

                    continue;
                }

                $profile->{$field} = $entry[$field];
                $changed = true;
            }

            if (! $changed) {
                continue;
            }

            $updated++;

            if ($dryRun) {
                continue;
            }

            $profile->save();
        }

        return ['updated' => $updated, 'skipped' => $skipped, 'missing' => $missing];
    }

    /**
     * The staff profile behind a team member. Matching prefers the login e-mail
     * seeded by {@see RealDataSeeder}; production may have had one changed by
     * hand, so an exact name match is the fallback. A user without a profile is
     * treated as unmatched — creating accounts is RealDataSeeder's job.
     */
    protected function resolveProfile(string $email, string $name): ?StaffProfile
    {
        $user = User::query()->where('email', $email)->first()
            ?? User::query()->where('name', $name)->first();

        return $user?->staffProfile;
    }

    /**
     * Every team member's published qualifications, in website order.
     *
     * @return list<array{
     *     name: string,
     *     email: string,
     *     education?: list<array{degree: string, institution: ?string, period: ?string}>,
     *     certifications?: list<array{name: string, institution: ?string, year: ?string}>,
     * }>
     */
    protected function qualifications(): array
    {
        return [
            [
                'name' => 'Lucie Fickerová',
                'email' => 'lucie.fickerova@friendlyfyzio.cz',
                'education' => [
                    $this->study('Bc., Fyzioterapie', 'Ostravská univerzita v Ostravě, Lékařská fakulta', '2009 – 2012'),
                    $this->study('Mgr., Fyzioterapie', 'Ostravská univerzita v Ostravě, Lékařská fakulta', '2012 – 2015'),
                ],
                'certifications' => [
                    $this->course('Klasické masáže', '2010'),
                    $this->course('Terapeutické využití kinesiotapu', '2011'),
                    $this->course('Spirální stabilizace (SM systém)', '2016'),
                    $this->course('Diagnostika a terapie funkčních poruch', '2017'),
                    $this->course('Léčba některých druhů funkční ženské sterility metodou Ludmily Mojžíšové', '2017'),
                    $this->course('Anatomy trains walking lines', '2018'),
                    $this->course('Poporodní diastáza – prevence, vyhodnocení, terapie', '2019'),
                    $this->course('Fyzioterapeutické metody a přístupy v těhotenství a po porodu', '2019'),
                    $this->course('Fyzioterapie u dysfunkce pánevního dna a inkontinence', '2019'),
                    $this->course('Porodní poranění hráze z pohledu porodní asistence a fyzioterapie', '2020'),
                    $this->course('Vhled do fyzioterapie těhotných žen v souladu s přístupem Fyzioterapie funkce', '2021'),
                    $this->course('Holistická péče o jizvu', '2022'),
                    $this->course('Diastáza – úvodní díl / aktivní terapie', '2022'),
                    $this->course('Rebozo ve fyzioterapii', '2022'),
                    $this->course('Aktivně v těhotenství', '2023'),
                    $this->course('Ultrazvuk v praxi fyzioterapeuta – urogynekologie', '2023'),
                    $this->course('Celostní přístup v terapii pánevního dna', '2024'),
                    $this->course('Celostní přístup v terapii jizev a adhezí z pohledu integrační fyzioterapie', '2024'),
                    $this->course('Pánev', '2025'),
                    $this->course('Možnosti fyzioterapie po rakovině prsu', '2026'),
                    // The site lists this one under "Semináře", with no year.
                    $this->course('Hormonální jógová terapie', null, 'Seminář'),
                ],
            ],
            [
                'name' => 'Renáta Prnka',
                'email' => 'renata.prnka@friendlyfyzio.cz',
                'education' => [
                    $this->study('Mgr., Fyzioterapie', 'Univerzita Palackého v Olomouci, Fakulta zdravotnických věd', '2011 – 2014'),
                    $this->study('Bc., Fyzioterapie', 'Ostravská univerzita v Ostravě, Lékařská fakulta', '2008 – 2011'),
                    // The site's "praxe" block.
                    $this->study('Cvičitel a fyzioterapeut voltižního oddílu', null, '2009 – 2016'),
                    $this->study('Externí vyučující, obor Fyzioterapie', 'Lékařská fakulta Ostravské univerzity', '2019 – dosud'),
                ],
                'certifications' => [
                    $this->course('Klasické masáže', '2009'),
                    $this->course('Míčková facilitace', '2010'),
                    $this->course('Kineziotaping', '2010'),
                    $this->course('Nespecifické mobilizace', '2011'),
                    $this->course('Redcord Medical Neurac 1', '2012'),
                    $this->course('SM Systém 1', '2013'),
                    $this->course('SM Systém 2', '2014'),
                    $this->course('SM Systém – aktivní léčba skoliózy', '2014'),
                    $this->course('Hipoterapie', '2015'),
                    $this->course('Aplikace metody Roswithy Brunkow', '2015'),
                    $this->course('Diagnostika a terapie kolenního kloubu', '2016'),
                    $this->course('Medical Taping Concept', '2017'),
                    $this->course('Fyzioterapeutické metody a přístupy v těhotenství a po porodu', '2021'),
                    $this->course('Fyzioterapie u dysfunkce pánevního dna a inkontinence', '2021'),
                    $this->course('Holistická péče o jizvu', '2022'),
                    $this->course('Diastáza – úvodní díl', '2022'),
                    $this->course('Diastáza – aktivní terapie', '2022'),
                    $this->course('Aktivně v těhotenství', '2023'),
                    $this->course('Ultrazvuk v praxi fyzioterapeuta – urogynekologie', '2023'),
                    $this->course('Temporomandibulární kloub – úvodní a pokročilá diagnostika', '2024'),
                    $this->course('Celostní přístup v terapii jizev a adhezí z pohledu integrační fyzioterapie', '2024'),
                    $this->course('Diagnostika a terapie ramenního kloubu', '2025'),
                    $this->course('Celostní přístup v terapii pánevního dna', '2025'),
                    $this->course('Mobilizace žeber metodou L. Mojžíšové', '2025'),
                    $this->course('Možnosti fyzioterapie po rakovině prsu', '2026'),
                    $this->course('Metoda Ludmily Mojžíšové (A–D)', '2026'),
                ],
            ],
            [
                'name' => 'Šárka Antošíková',
                'email' => 'sarka.antosikova@friendlyfyzio.cz',
                'education' => [
                    $this->study('Obor fyzioterapeut', 'Střední zdravotnická škola v Ostravě', null),
                    $this->study('Pomaturitní specializační studium léčebné tělesné výchovy, obor fyzioterapeut', null, '2002 – 2003'),
                ],
                'certifications' => [
                    $this->course('Diagnostika a terapie funkčních poruch', '2002'),
                    $this->course('Reflexní terapie plosky nohy', '2004'),
                    $this->course('Mobilizace žeber metodou Ludmily Mojžíšové', '2006'),
                    $this->course('Metoda Roswithy Brunkow', '2010'),
                    $this->course('Terapeutické využití kinesiotejpu', '2012'),
                    $this->course('Spirální stabilizace SM systém', '2015'),
                    $this->course('Fyzioterapie u dysfunkce pánevního dna a inkontinence', '2016'),
                    $this->course('Inkontinence u mužů, anorectální a sexuální dysfunkce', '2018'),
                    $this->course('Holistická péče o jizvu', '2022'),
                    $this->course('Diastáza – úvodní díl', '2022'),
                    $this->course('Temporomandibulární kloub – úvodní a pokročilá diagnostika', '2023'),
                    $this->course('3D funkce nohy ve spojení s kyčelním kloubem', '2023'),
                    $this->course('Celostní přístup v terapii jizev a adhezí z pohledu integrační fyzioterapie', '2024'),
                    $this->course('Možnosti fyzioterapie po rakovině prsu', '2026'),
                    $this->course('Stimulace a facilitace vitálních funkcí', '2026'),
                ],
            ],
            [
                'name' => 'Lada Činčilová',
                'email' => 'lada.cincilova@friendlyfyzio.cz',
                'education' => [
                    $this->study('Bc., Fyzioterapie', 'Masarykova univerzita Brno, Lékařská fakulta', '2019 – 2022'),
                ],
                'certifications' => [
                    $this->course('Bolest v oblasti kolenního kloubu', '2020'),
                    $this->course('Kinesiotape – Rocktape', '2021'),
                    $this->course('Holistická péče o jizvu', '2022'),
                    $this->course('Ramenní kloub v kontextu celkové postury', '2023'),
                    $this->course('Aktivně v těhotenství', '2023'),
                    $this->course('Akrální koaktivační terapie', '2023'),
                    $this->course('Temporomandibulární kloub – úvodní a pokročilá diagnostika', '2023'),
                    $this->course('SM Systém', '2024'),
                    $this->course('Metoda L. Mojžíšové', '2024'),
                    $this->course('Terapie u dysfunkcí pánevního dna', '2024'),
                    $this->course('Celostní přístup v terapii jizev a adhezí z pohledu integrační fyzioterapie', '2024'),
                    $this->course('Access Bars', '2025'),
                    $this->course('Celostní přístup v terapii pánevního dna', '2025'),
                    $this->course('3D funkce nohy', '2026'),
                ],
            ],
            [
                'name' => 'Ema Murčová',
                'email' => 'ema.murcova@friendlyfyzio.cz',
                'education' => [
                    $this->study('Bc., Fyzioterapie', 'Ostravská univerzita v Ostravě, Lékařská fakulta', '2021 – 2025'),
                ],
                'certifications' => [
                    $this->course('Sportovní masáže', '2023'),
                    $this->course('Instruktor a kondiční trenér pro ženskou klientelu', '2023'),
                    $this->course('Aktivně v těhotenství', '2025'),
                    $this->course('SM systém', '2025'),
                    $this->course('Diastáza', '2025'),
                    $this->course('Fyzioterapie v prekoncepci, těhotenství a po porodu', '2025'),
                    $this->course('Jizva', '2026'),
                ],
            ],
            [
                'name' => 'Daniela Steblová',
                'email' => 'daniela.steblova@friendlyfyzio.cz',
                'education' => [
                    $this->study('Mgr., Fyzioterapie', 'Ostravská univerzita v Ostravě, Lékařská fakulta', '2020 – 2023'),
                    $this->study('Bc., Fyzioterapie', 'Univerzita Palackého v Olomouci, Fakulta zdravotnických věd', '2017 – 2020'),
                    $this->study('Postgraduální studium angličtiny', 'Tutor', '2016 – 2017'),
                    // The site's "Stáže" block.
                    $this->study('Studium na University of Central Arkansas', 'UCA Conway', 'srpen 2021 – prosinec 2021'),
                    $this->study('Stáž ve fyzioterapii', 'Wingate Netanya a Maccabi Tel-Aviv, Izrael', 'leden 2019 – březen 2019'),
                ],
                'certifications' => [
                    $this->course('Metoda V. Vojty – kurz A', '2023', 'RL-Corpus'),
                    $this->course('Pánevní dno', '2023', 'Groofy'),
                    $this->course('Předporodní kurz', '2024', 'Porod je krásný'),
                    $this->course('Možnosti plného využití myofeedbacku (seminář)', '2024', 'Gymna training, Patrick de Rock'),
                    $this->course('Ultrazvuk v praxi fyzioterapeuta', '2024', 'REHASPRINGcentrum s.r.o.'),
                    $this->course('Pánev – úvodní díl', '2025', 'Groofy'),
                    $this->course('Metoda Ludmily Mojžíšové (A–D)', '2026'),
                    // The site's "Konference" block.
                    $this->course('Konference POROD', '2024'),
                    $this->course('Konference POROD', '2025'),
                ],
            ],
        ];
    }

    /**
     * @return array{degree: string, institution: ?string, period: ?string}
     */
    protected function study(string $degree, ?string $institution, ?string $period): array
    {
        return ['degree' => $degree, 'institution' => $institution, 'period' => $period];
    }

    /**
     * @return array{name: string, institution: ?string, year: ?string}
     */
    protected function course(string $name, ?string $year, ?string $institution = null): array
    {
        return ['name' => $name, 'institution' => $institution, 'year' => $year];
    }
}
