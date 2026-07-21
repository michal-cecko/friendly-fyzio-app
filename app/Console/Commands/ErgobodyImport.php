<?php

namespace App\Console\Commands;

use App\Enums\Gender;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\ServiceVisibility;
use App\Enums\UserRole;
use App\Models\ClientNote;
use App\Models\ClientProfile;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One-off import of the Ergobody.cz practice-management exports.
 *
 * What it produces:
 *  - Kartotéka  → users + client profiles
 *  - Terapie    → client notes, each also rebuilt into a past reservation
 *  - Přehled    → the client's Anamnéza field (newest entry), older ones as notes
 *  - Presety    → nothing; see "Skipped data" below
 *
 * Idempotent: clients are keyed by e-mail, notes by (client, timestamp, content
 * hash), reservations by whether their note is already linked. Re-running fills
 * gaps rather than duplicating.
 *
 * ---------------------------------------------------------------------------
 * The rules below encode decisions taken with the owner while reconciling this
 * export. They are not derivable from the data alone — do not "simplify" them.
 * ---------------------------------------------------------------------------
 *
 * Identity: cards sharing a name are one person when they share a birth date OR
 * a phone number. Ergobody typos land in the birth date or the e-mail but
 * effectively never in the phone (Stanislava Knapová's two cards read 1992-07-03
 * and 1972-07-03 — same day and month, one decade apart — on one phone). That
 * rule merged 19 duplicate pairs while keeping every genuine namesake apart,
 * because real namesakes never share a phone.
 *
 * {@see STAFF_CARDS}: seven Kartotéka cards are staff treated at the practice
 * (owner-confirmed). Their patient data attaches to the existing staff account;
 * their name, role and login e-mail are never touched. Cards that merely share a
 * staff name — Šárka b. 1967, Kristýna the *sudkyňa*, Adéla the *překladatelka*,
 * and a card filed under Lucie's name with Lada's e-mail — are ordinary clients.
 *
 * {@see NOTE_OVERRIDES}: only for notes that two genuine namesakes could both
 * claim because their treatment windows overlap. Each entry records the evidence
 * that settled it (a card's therapy count matching a run of notes, a Caesarean,
 * a Slovak "36+6tt" pregnancy series, "Kondiční terapie MH"). Never extend this
 * list by guessing — leave a note unassigned instead.
 *
 * Same-first-name staff are different people, not renames (owner-confirmed):
 * Renáta Prnka ≠ Renáta Dojcsánová — they signed notes in the same months for 20
 * consecutive months, so no name change is possible; Michaela Hrubá (conditioning
 * trainer) ≠ Michaela Carbolová (masseuse); Lucie Fickerová ≠ Lucie Amani. Do not
 * merge them.
 *
 * Signatures were hand-typed for two and a half years, so a bare surname or first
 * name is only trusted when unique across all staff — two Lucies, two Michaelas
 * and two Renátas exist. In practice no note is signed with a bare Lucie, Renáta
 * or Michaela anyway; the only bare first names are Denisa (758) and Lada (2).
 *
 * No e-mail can result from this import, and that must stay true. Every
 * mail-sending scheduled command is scoped to `reservation_date >= today`
 * (confirmations, reminders, auto-cancel), `payments:mark-overdue` reads Payment
 * rows and none are created, `reviews:send-requests` only covers courses and
 * one-off events, `reservations:settle-past` skips rows whose `settled_at` is
 * already set, and every write here is a raw insert so no observer fires.
 * Anyone adding a scheduled command that touches past reservations must re-check
 * this. Activity logging is disabled for the same reason: otherwise the audit
 * trail would gain tens of thousands of rows.
 *
 * Historical visits invent no money: they are filed under a hidden, unpublished,
 * price-0 service, so nothing is owed and no revenue appears, and they settle
 * without a single Payment row. Their room is null (unknown — claiming a real
 * room would fake occupancy) and their times are placeholders assigned at import,
 * which is exactly why `imported_at` exists and why the calendar filters them out.
 *
 * Skipped data: Presety were preset examination protocols (Bederní páteř,
 * Základní – celé tělo). Only 3 of 1 318 rows ever had test values before the
 * feature was abandoned in 2024, and the owner chose not to import them.
 */
class ErgobodyImport extends Command
{
    protected $signature = 'ergobody:import
        {path=export/ergobody : Directory containing the Ergobody CSV exports}
        {--dry-run : Parse and report without writing anything}';

    protected $description = 'Importuje klienty a terapeutické záznamy z exportů Ergobody.';

    public const string IMPORT_TAG = 'Ergobody import';

    /**
     * Therapists who appear as note signatures but no longer work at the
     * practice: created as deactivated accounts so their notes stay attributed.
     *
     * @var array<string, string> name => e-mail
     */
    protected const array FORMER_THERAPISTS = [
        'Renáta Dojcsánová' => 'renata.dojcsanova@friendlyfyzio.cz',
        'Zuzana Goldman' => 'zuzana.goldman@friendlyfyzio.cz',
        'Karolína Vasevičová' => 'karolina.vasevicova@friendlyfyzio.cz',
        'Klára Šimšálková' => 'klara.simsalkova@friendlyfyzio.cz',
        'Vendula Janošová' => 'vendula.janosova@friendlyfyzio.cz',
        'Michaela Carbolová' => 'michaela.carbolova@friendlyfyzio.cz',
    ];

    /**
     * Initials used in place of a signature.
     *
     * @var array<string, string> lowercased initials => staff name
     */
    protected const array SIGNATURE_INITIALS = [
        'mh' => 'Michaela Hrubá',
    ];

    /**
     * Signature spellings that differ from the account name.
     *
     * @var array<string, string> normalized alias => canonical name
     */
    protected const array SIGNATURE_ALIASES = [
        'lucka fickerová' => 'Lucie Fickerová',
    ];

    /**
     * Kartotéka cards that are a staff member being treated at the practice
     * (confirmed by the owner), rather than a client who happens to share the
     * name. Their patient data attaches to the existing staff account.
     *
     * @var array<string, string> Ergobody card e-mail => staff login e-mail
     */
    protected const array STAFF_CARDS = [
        'info+3072@ergobody.cz' => 'lucie.fickerova@friendlyfyzio.cz',
        'info+3929@ergobody.cz' => 'sarka.antosikova@friendlyfyzio.cz',
        'info+5073@ergobody.cz' => 'lada.cincilova@friendlyfyzio.cz',
        'emamurcova@gmail.com' => 'ema.murcova@friendlyfyzio.cz',
        'kirsten.cerna@gmail.com' => 'kristyna.cerna@friendlyfyzio.cz',
        'novakovadeni@seznam.cz' => 'denisa.novakova@friendlyfyzio.cz',
        'info+10323@ergobody.cz' => 'renata.prnka@friendlyfyzio.cz',
    ];

    /**
     * Notes that two genuine namesakes could both claim, because their
     * treatment periods overlap and the export records only a name and a date.
     * Each was read out of the note text and cross-checked against the therapy
     * count on the card, so every card ends up with the number of visits
     * Ergobody recorded for it.
     *
     * @var array<string, string> "normalized name|Y-m-d" => Ergobody card e-mail
     */
    protected const array NOTE_OVERRIDES = [
        // Šárka the physiotherapist came in for routine massages from Renáta
        // Dojcsánová (8 visits, all signed by her); the 1967-born namesake has
        // the clinical hip/scapula series and later massages by Carbolová.
        'šárka antošíková|2024-04-17' => 'info+3929@ergobody.cz',
        'šárka antošíková|2024-06-19' => 'info+3929@ergobody.cz',
        'šárka antošíková|2024-12-10' => 'info+3929@ergobody.cz',
        'šárka antošíková|2025-03-07' => 'info+3929@ergobody.cz',
        'šárka antošíková|2025-04-24' => 'info+3929@ergobody.cz',
        'šárka antošíková|2025-05-16' => 'info+3929@ergobody.cz',
        'šárka antošíková|2025-06-13' => 'info+3929@ergobody.cz',
        'šárka antošíková|2024-03-28' => 'info+4057@ergobody.cz',
        'šárka antošíková|2024-04-04' => 'info+4057@ergobody.cz',
        'šárka antošíková|2025-04-03' => 'info+4057@ergobody.cz',
        'šárka antošíková|2025-05-22' => 'info+4057@ergobody.cz',

        // The Slovak-language pregnancy series (19tt → 34+4tt) belongs to the
        // 1992-born namesake; the relaxation massage is the yoga instructor's
        // fifth and last recorded visit.
        'kristýna černá|2025-07-09' => 'kristynavrch@gmail.com',
        'kristýna černá|2025-07-23' => 'kristynavrch@gmail.com',
        'kristýna černá|2025-08-20' => 'kristynavrch@gmail.com',
        'kristýna černá|2025-10-29' => 'kristynavrch@gmail.com',
        'kristýna černá|2025-10-14' => 'kirsten.cerna@gmail.com',

        // Two of the three Michaela cards are one person (shared phone and
        // birth date); the pregnancy massage and the Slovak "36+6tt" entry are
        // the third woman's two visits.
        'michaela pavelková|2025-09-16' => 'misadrastikova@seznam.cz',
        'michaela pavelková|2025-10-22' => 'misadrastikova@seznam.cz',
        'michaela pavelková|2025-10-01' => 'm.pavelkova99@gmail.com',

        // A Caesarean in 2022 and maternity leave point at the younger Lenka;
        // the 2023 note predates her card entirely.
        'lenka skanderová|2023-10-03' => 'lenka.skanderova@centrum.cz',
        'lenka skanderová|2025-02-24' => 'lenka.skanderova@vsb.cz',

        // "Kondiční terapie MH" matches the student's card, opened a day later.
        'radim stroka|2024-06-12' => 'sisi.pavelkova@seznam.cz',
    ];

    protected bool $dryRun = false;

    /** @var array<string, int> */
    protected array $stats = [];

    /** @var array<string, int> */
    protected array $unmatchedSignatures = [];

    /** @var array<string, int> */
    protected array $unmatchedClients = [];

    /**
     * Kartotéka's free-text "Popis/Indispozice" per client (8 rows in the real
     * export, but they hold things like allergies). Kept aside so a later
     * anamnesis entry can be prepended without discarding it.
     *
     * @var array<string, string>
     */
    protected array $cardDescriptions = [];

    /**
     * Ids of the notes this import owns (created now or by an earlier run).
     * Only these may generate a historical reservation — a note a therapist
     * writes in the app later must never turn into an invented past visit.
     *
     * @var list<string>
     */
    protected array $importedNoteIds = [];

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        $directory = $this->argument('path');

        if (! str_starts_with($directory, '/')) {
            $directory = base_path($directory);
        }

        $files = [
            'kartoteka' => $this->findCsv($directory, 'Kartot'),
            'terapie' => $this->findCsv($directory, 'Terapie'),
            'prehled' => $this->findCsv($directory, 'ehled'),
        ];

        if (! $files['kartoteka']) {
            $this->error("Kartotéka export not found in {$directory}.");

            return self::FAILURE;
        }

        if ($this->dryRun) {
            $this->warn('Dry run — no changes will be written.');
        }

        // Bulk import must not flood the audit trail with thousands of rows.
        activity()->disableLogging();

        $clientsByName = $this->importClients($files['kartoteka']);
        $authorsByName = $this->resolveAuthors();

        if ($files['terapie']) {
            $this->importNotes($files['terapie'], $clientsByName, $authorsByName, 'terapie');
        }

        if ($files['prehled']) {
            $this->importAnamneses($files['prehled'], $clientsByName, $authorsByName);
        }

        // Each therapy note records a visit that actually happened, so the
        // appointment history is rebuilt from them once they all exist.
        $this->rebuildHistoricalReservations();

        activity()->enableLogging();

        $this->printSummary();

        return self::SUCCESS;
    }

    protected function findCsv(string $directory, string $needle): ?string
    {
        foreach (glob($directory.'/*.csv') ?: [] as $file) {
            if (str_contains(basename($file), $needle)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Import the Kartotéka client cards into users + client profiles.
     *
     * Ergobody let the same person be entered twice (a second card after a typo
     * in the e-mail or birth date), so cards sharing a name are first clustered
     * into real people — see {@see clusterCards()} — and each cluster becomes a
     * single client. Cards belonging to staff treated at the practice attach to
     * the existing staff account instead of creating a second person.
     *
     * @return array<string, list<array{user_id: ?string, emails: list<string>, start: ?Carbon, end: ?Carbon}>>
     *                                                                                                          normalized client name => the people behind it
     */
    protected function importClients(string $file): array
    {
        /** @var array<string, list<array<string, string>>> $byName */
        $byName = [];

        foreach ($this->readCsv($file) as $row) {
            $name = $this->normalizeWhitespace(($row['Jméno'] ?? '').' '.($row['Přijmení'] ?? ''));

            if ($name === '') {
                $this->bump('clients_invalid');

                continue;
            }

            $row['__name'] = $name;
            $byName[$this->normalizeName($name)][] = $row;
        }

        $people = [];

        foreach ($byName as $key => $rows) {
            foreach ($this->clusterCards($rows) as $cluster) {
                if (count($cluster) > 1) {
                    $this->bump('duplicate_cards_merged');
                }

                $people[$key][] = $this->importCard($cluster);
            }
        }

        return $people;
    }

    /**
     * Split same-name cards into distinct people. Two cards are the same person
     * when they share a birth date or a phone number — in practice the typo is
     * never in both at once — while genuine namesakes share neither.
     *
     * @param  list<array<string, string>>  $rows
     * @return list<list<array<string, string>>>
     */
    protected function clusterCards(array $rows): array
    {
        $parent = range(0, count($rows) - 1);

        $find = function (int $i) use (&$parent): int {
            while ($parent[$i] !== $i) {
                $parent[$i] = $parent[$parent[$i]];
                $i = $parent[$i];
            }

            return $i;
        };

        foreach ($rows as $i => $row) {
            foreach (array_slice($rows, $i + 1, preserve_keys: true) as $j => $other) {
                $birthDate = $this->cleanValue($row['Datum narození'] ?? '');
                $otherBirthDate = $this->cleanValue($other['Datum narození'] ?? '');
                $phone = $this->normalizePhone($row['Telefon'] ?? '');
                $otherPhone = $this->normalizePhone($other['Telefon'] ?? '');

                if (($birthDate && $birthDate === $otherBirthDate) || ($phone && $phone === $otherPhone)) {
                    $parent[$find($i)] = $find($j);
                }
            }
        }

        $clusters = [];

        foreach ($rows as $i => $row) {
            $clusters[$find($i)][] = $row;
        }

        return array_values($clusters);
    }

    /**
     * Create (or update) the single client behind one cluster of cards.
     *
     * @param  list<array<string, string>>  $cluster
     * @return array{user_id: ?string, emails: list<string>, start: ?Carbon, end: ?Carbon}
     */
    protected function importCard(array $cluster): array
    {
        // The card with the most therapies carries the fullest history, so it
        // wins on conflicting values; the others only fill in its blanks.
        usort($cluster, fn (array $a, array $b): int => (int) ($b['Počet terapii'] ?? 0) <=> (int) ($a['Počet terapii'] ?? 0));

        $pick = function (string $column) use ($cluster): ?string {
            foreach ($cluster as $row) {
                if ($value = $this->cleanValue($row[$column] ?? '')) {
                    return $value;
                }
            }

            return null;
        };

        $primary = $cluster[0];
        $name = $primary['__name'];
        $emails = array_values(array_filter(array_map(
            fn (array $row): string => mb_strtolower(trim($row['Email'] ?? '')),
            $cluster,
        )));

        $dates = array_filter(array_map(
            fn (array $row): ?Carbon => $this->parseDate($row['Datum vytvoření'] ?? '', 'Y-m-d'),
            $cluster,
        ));
        $lastVisits = array_filter(array_map(
            fn (array $row): ?Carbon => $this->parseDate($row['Poslední terapie'] ?? '', 'Y-m-d'),
            $cluster,
        ));

        $start = $dates === [] ? null : min($dates);
        $end = $lastVisits === [] ? $start : max($lastVisits);

        $staffUser = $this->staffAccountFor($emails);
        $window = ['emails' => $emails, 'start' => $start, 'end' => $end];

        if ($staffUser) {
            $this->bump('staff_cards_attached');

            if ($this->dryRun) {
                return ['user_id' => 'dry:'.$emails[0], ...$window];
            }

            // Staff treated here keep one account: only the patient-side data
            // is attached, never their name, role or login e-mail.
            $user = $staffUser;
        } else {
            $email = $this->normalizeEmail($primary['Email'] ?? '', $name);
            $user = User::query()->where('email', $email)->first() ?? new User;

            // Reported honestly so a re-run doesn't look like a fresh import.
            $this->bump($user->exists ? 'clients_updated' : 'clients_created');

            if ($this->dryRun) {
                return ['user_id' => 'dry:'.$emails[0], ...$window];
            }

            $user->fill([
                'name' => $name,
                'email' => $email,
                'phone' => $this->normalizePhone($pick('Telefon') ?? ''),
                'role' => UserRole::Customer,
            ]);

            if (! $user->exists) {
                $user->forceFill(['password' => Str::password(40)]);
            }

            $user->save();

            if ($start) {
                $user->forceFill(['created_at' => $start->copy()->startOfDay()])->saveQuietly();
            }
        }

        ClientProfile::query()->updateOrCreate(
            ['user_id' => $user->getKey()],
            [
                'date_of_birth' => $this->parseDate($pick('Datum narození') ?? '', 'Y-m-d')?->toDateString(),
                'gender' => $this->mapGender($pick('Pohlaví') ?? ''),
                'birth_number' => $pick('Rodné číslo'),
                'address_city' => $pick('Adresa'),
                'occupation' => $pick('Pracovní pozice'),
                'weight' => $this->parseMeasurement($pick('Hmotnost (kg)') ?? ''),
                'height' => $this->parseMeasurement($pick('Výška (cm)') ?? ''),
                'anamnesis' => $this->textToHtml($pick('Popis/Indispozice') ?? ''),
            ],
        );

        // The tag both marks provenance and counts as "activity" for
        // users:prune-unverified, protecting note-less imported clients.
        $user->attachTag(self::IMPORT_TAG);

        if ($description = $pick('Popis/Indispozice')) {
            $this->cardDescriptions[$user->getKey()] = $description;
        }

        return ['user_id' => $user->getKey(), ...$window];
    }

    /**
     * The staff account a Kartotéka card belongs to, when that card is a staff
     * member being treated at the practice rather than a namesake.
     *
     * @param  list<string>  $emails
     */
    protected function staffAccountFor(array $emails): ?User
    {
        foreach ($emails as $email) {
            if ($staffEmail = self::STAFF_CARDS[$email] ?? null) {
                $user = User::query()->where('email', $staffEmail)->first();

                if (! $user) {
                    $this->warn("Staff account {$staffEmail} not found — run RealDataSeeder first.");
                }

                return $user;
            }
        }

        return null;
    }

    /**
     * Map of normalized signature names to author user ids, creating
     * deactivated accounts for former therapists on the way.
     *
     * @return array<string, string>
     */
    protected function resolveAuthors(): array
    {
        if (! $this->dryRun) {
            foreach (self::FORMER_THERAPISTS as $name => $email) {
                $user = User::query()->firstOrNew(['email' => $email]);

                if (! $user->exists) {
                    $user->fill(['name' => $name, 'role' => UserRole::Therapist]);
                    $user->forceFill([
                        'password' => Str::password(40),
                        'deactivated_at' => now(),
                    ]);
                    $user->save();
                    $this->bump('former_therapists_created');
                }
            }
        }

        $full = $surnames = $firstNames = [];
        $surnameCounts = $firstNameCounts = [];

        $staff = User::query()->where('role', '!=', UserRole::Customer)->get(['id', 'name']);

        foreach ($staff as $user) {
            $normalized = $this->normalizeSignature($user->name);
            $full[$normalized] = $user->getKey();
            $full[$this->reverseName($normalized)] = $user->getKey();

            $parts = explode(' ', $normalized);
            $first = reset($parts);
            $surname = end($parts);

            $surnames[$surname] = $user->getKey();
            $firstNames[$first] = $user->getKey();
            $surnameCounts[$surname] = ($surnameCounts[$surname] ?? 0) + 1;
            $firstNameCounts[$first] = ($firstNameCounts[$first] ?? 0) + 1;
        }

        foreach (self::SIGNATURE_ALIASES as $alias => $canonical) {
            if (isset($full[$this->normalizeSignature($canonical)])) {
                $full[$this->normalizeSignature($alias)] = $full[$this->normalizeSignature($canonical)];
            }
        }

        foreach (self::SIGNATURE_INITIALS as $initials => $name) {
            if (isset($full[$this->normalizeSignature($name)])) {
                $full[$initials] = $full[$this->normalizeSignature($name)];
            }
        }

        // A bare surname or first name only identifies someone when no other
        // staff member shares it — several Lucies and Michaelas have worked here.
        return [
            'full' => $full,
            'surname' => array_filter($surnames, fn (string $key): bool => $surnameCounts[$key] === 1, ARRAY_FILTER_USE_KEY),
            'first' => array_filter($firstNames, fn (string $key): bool => $firstNameCounts[$key] === 1, ARRAY_FILTER_USE_KEY),
        ];
    }

    /**
     * Import Terapie / Přehled rows as client notes.
     *
     * @param  array<string, list<string>>  $clientsByName
     * @param  array<string, string>  $authorsByName
     */
    protected function importNotes(string $file, array $clientsByName, array $authorsByName, string $kind): void
    {
        $existing = $this->existingNoteKeys();
        $sequence = [];
        $inserts = [];

        foreach ($this->readCsv($file) as $row) {
            $note = $this->mapTherapyRow($row);

            if ($note === null) {
                $this->bump("{$kind}_invalid");

                continue;
            }

            $clientKey = $this->normalizeName($note['client']);
            $candidates = $clientsByName[$clientKey] ?? [];
            $clientId = $this->matchClient($clientKey, $note['date'], $candidates);

            if ($clientId === null) {
                $this->unmatchedClients[$note['client']] = ($this->unmatchedClients[$note['client']] ?? 0) + 1;
                $this->bump(count($candidates) > 1 ? "{$kind}_ambiguous_client" : "{$kind}_unmatched_client");

                continue;
            }

            $author = $this->resolveSignature($note['signature_source'] ?? $note['content'], $authorsByName);

            // Session notes sit at midday, after that day's anamnesis entry;
            // same-day notes are offset by a second to stay ordered.
            $baseHour = 12;
            $sequenceKey = $clientId.$note['date']->toDateString();
            $seq = $sequence[$sequenceKey] ?? 0;
            $sequence[$sequenceKey] = $seq + 1;
            $createdAt = $note['date']->copy()->setTime($baseHour, 0)->addSeconds($seq);

            $content = $this->textToHtml($note['content'], $note['heading'] ?? null);

            $key = $clientId.'|'.$createdAt->format('Y-m-d H:i:s').'|'.md5($content);

            if (isset($existing[$key])) {
                // Already imported by an earlier run — still ours, so it stays
                // eligible for the reservation rebuild.
                $this->importedNoteIds[] = $existing[$key];
                $this->bump("{$kind}_existing");

                continue;
            }

            if ($this->dryRun) {
                $this->bump("{$kind}_imported");

                continue;
            }

            $id = (new ClientNote)->newUniqueId();
            $existing[$key] = $id;
            $this->importedNoteIds[] = $id;
            $this->bump("{$kind}_imported");

            $inserts[] = [
                'id' => $id,
                'client_id' => $clientId,
                'author_id' => $author,
                'reservation_id' => null,
                'content' => $content,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
        }

        $this->insertNotes($inserts);
    }

    /**
     * Anamnesis entries describe the client, not a visit, so the newest one
     * fills the client's Anamnéza field. Earlier entries stay as dated notes:
     * they routinely carry history (old injuries, operations) that the newest
     * write-up leaves out.
     *
     * @param  array<string, list<array{user_id: ?string, emails: list<string>, start: ?Carbon, end: ?Carbon}>>  $clientsByName
     * @param  array{full: array<string, string>, surname: array<string, string>, first: array<string, string>}  $authorsByName
     */
    protected function importAnamneses(string $file, array $clientsByName, array $authorsByName): void
    {
        /** @var array<string, list<array{date: Carbon, content: string}>> $byClient */
        $byClient = [];

        foreach ($this->readCsv($file) as $row) {
            $entry = $this->mapOverviewRow($row);

            if ($entry === null) {
                $this->bump('prehled_invalid');

                continue;
            }

            $clientKey = $this->normalizeName($entry['client']);
            $candidates = $clientsByName[$clientKey] ?? [];
            $clientId = $this->matchClient($clientKey, $entry['date'], $candidates);

            if ($clientId === null) {
                $this->unmatchedClients[$entry['client']] = ($this->unmatchedClients[$entry['client']] ?? 0) + 1;
                $this->bump(count($candidates) > 1 ? 'prehled_ambiguous_client' : 'prehled_unmatched_client');

                continue;
            }

            $byClient[$clientId][] = $entry;
        }

        $existing = $this->existingNoteKeys();
        $inserts = [];

        foreach ($byClient as $clientId => $entries) {
            usort($entries, fn (array $a, array $b): int => $a['date'] <=> $b['date']);

            $newest = array_pop($entries);
            $this->bump('anamneses_set');

            if (! $this->dryRun) {
                // Mass update on purpose: no model events, so no audit rows.
                // Composed from the source data rather than from the stored
                // value, so re-running can never stack duplicates.
                ClientProfile::query()
                    ->where('user_id', $clientId)
                    ->update(['anamnesis' => $this->anamnesisHtml($newest, $this->cardDescriptions[$clientId] ?? null)]);
            }

            // Whatever came before the newest entry is kept as history.
            foreach ($entries as $index => $entry) {
                $createdAt = $entry['date']->copy()->setTime(8, 0)->addSeconds($index);
                $content = $this->anamnesisHtml($entry);
                $key = $clientId.'|'.$createdAt->format('Y-m-d H:i:s').'|'.md5($content);

                if (isset($existing[$key])) {
                    $this->bump('anamneses_archived_existing');

                    continue;
                }

                $existing[$key] = true;
                $this->bump('anamneses_archived');

                if ($this->dryRun) {
                    continue;
                }

                $inserts[] = [
                    'id' => (new ClientNote)->newUniqueId(),
                    'client_id' => $clientId,
                    'author_id' => null,
                    'reservation_id' => null,
                    'content' => $content,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
            }
        }

        $this->insertNotes($inserts);
    }

    /**
     * Rebuilds the appointment history from the imported therapy notes: each
     * note records a visit that actually took place, so it becomes a settled,
     * past reservation linked back to the note.
     *
     * No e-mail can result from this. Every mail-sending scheduled command is
     * scoped to `reservation_date >= today` (confirmations, reminders,
     * auto-cancel), `payments:mark-overdue` reads Payment rows and we create
     * none, `reviews:send-requests` only covers courses and one-off events, and
     * the rows are written with a raw insert so no observer ever fires.
     */
    protected function rebuildHistoricalReservations(): void
    {
        if ($this->importedNoteIds === [] || $this->dryRun) {
            return;
        }

        $notes = ClientNote::query()
            ->whereKey($this->importedNoteIds)
            ->whereNull('reservation_id')
            ->whereNotNull('author_id')
            ->orderBy('created_at')
            ->get(['id', 'client_id', 'author_id', 'created_at']);

        // An unsigned note names nobody, and a visit filed under a guessed
        // therapist would be worse than no visit at all.
        $unsigned = ClientNote::query()
            ->whereKey($this->importedNoteIds)
            ->whereNull('reservation_id')
            ->whereNull('author_id')
            ->count();

        if ($unsigned > 0) {
            $this->stats['reservations_skipped_unsigned'] = $unsigned;
        }

        if ($notes->isEmpty()) {
            return;
        }

        $service = $this->historicalService();
        $therapistByUser = $this->historicalStaffProfiles($notes->pluck('author_id')->unique());

        $taken = $this->takenSlots();
        $inserts = [];
        $links = [];

        foreach ($notes as $note) {
            $therapistId = $therapistByUser[$note->author_id] ?? null;

            if ($therapistId === null) {
                // Signed by someone with no therapist profile (e.g. an assistant
                // running devices): the note stands, but inventing a visit under
                // the wrong therapist would corrupt the history.
                $this->bump('reservations_skipped_no_therapist');

                continue;
            }

            $date = $note->created_at->copy()->startOfDay();
            $start = $this->nextFreeSlot($taken, $therapistId, $date);

            if ($start === null) {
                $this->bump('reservations_skipped_no_slot');

                continue;
            }

            $this->bump('reservations_created');

            if ($this->dryRun) {
                continue;
            }

            $id = (new Reservation)->newUniqueId();

            $inserts[] = [
                'id' => $id,
                'client_id' => $note->client_id,
                'service_id' => $service->getKey(),
                'therapist_id' => $therapistId,
                'room_id' => null,
                'reservation_date' => $date->toDateString(),
                'start_time' => $start->format('H:i:s'),
                'end_time' => $start->copy()->addMinutes(30)->format('H:i:s'),
                'status' => ReservationStatus::Confirmed->value,
                'payment_status' => PaymentStatus::Paid->value,
                'is_control_therapy' => false,
                'notes' => null,
                'settled_at' => $date->copy()->setTime(12, 0),
                'imported_at' => now(),
                'created_at' => $note->created_at,
                'updated_at' => $note->created_at,
            ];

            $links[$id] = $note->id;
        }

        // Raw inserts again: the observer would scan for @-mentions and clear
        // day-waitlist entries, and the audit trail would gain 6k+ rows.
        foreach (array_chunk($inserts, 500) as $chunk) {
            Reservation::query()->insert($chunk);
        }

        foreach (array_chunk($links, 500, preserve_keys: true) as $chunk) {
            foreach ($chunk as $reservationId => $noteId) {
                ClientNote::query()->whereKey($noteId)->update(['reservation_id' => $reservationId]);
            }
        }
    }

    /**
     * The next unused half-hour slot for a therapist on a day, starting at 08:00.
     * Times are placeholders — the export records no time of day — but they must
     * stay unique per therapist and day to satisfy the no-double-booking index.
     *
     * @param  array<string, bool>  $taken
     */
    protected function nextFreeSlot(array &$taken, string $therapistId, Carbon $date): ?Carbon
    {
        $slot = $date->copy()->setTime(8, 0);
        $endOfDay = $date->copy()->setTime(21, 0);

        while ($slot->lessThan($endOfDay)) {
            $key = $therapistId.'|'.$date->toDateString().'|'.$slot->format('H:i:s');

            if (! isset($taken[$key])) {
                $taken[$key] = true;

                return $slot;
            }

            $slot->addMinutes(30);
        }

        return null;
    }

    /**
     * Slots already occupied, so re-runs and any real bookings are never
     * double-booked by the import.
     *
     * @return array<string, bool>
     */
    protected function takenSlots(): array
    {
        $taken = [];

        foreach (Reservation::query()->select('therapist_id', 'reservation_date', 'start_time')->cursor() as $reservation) {
            $date = Carbon::parse($reservation->reservation_date)->toDateString();
            $time = Carbon::parse($reservation->start_time)->format('H:i:s');
            $taken[$reservation->therapist_id.'|'.$date.'|'.$time] = true;
        }

        return $taken;
    }

    /**
     * The catch-all service historical visits are filed under. Hidden and
     * unpublished so `Service::scopeBookable()` can never offer it, and priced
     * at 0 so the visits owe nothing — no invented revenue, no invented debt,
     * and they settle without a single Payment row.
     */
    protected function historicalService(): Service
    {
        return Service::query()->firstOrCreate(
            ['slug' => 'historicka-terapie'],
            [
                'name' => 'Historická terapie',
                'invoice_title' => 'Historická terapie',
                'duration_minutes' => 30,
                'price' => 0,
                'break_minutes' => 0,
                'visibility' => ServiceVisibility::Hidden,
                'published_at' => null,
            ],
        );
    }

    /**
     * Therapist-profile ids keyed by user id. Former staff have no profile —
     * one is created, unpublished and without services, purely so the
     * reservation foreign key resolves. They stay unbookable: the wizard only
     * offers profiles that have a bookable service.
     *
     * @param  Collection<int, string>  $userIds
     * @return array<string, string>
     */
    protected function historicalStaffProfiles(Collection $userIds): array
    {
        $profiles = [];

        foreach (User::query()->whereKey($userIds)->therapists()->get() as $user) {
            $profile = $user->staffProfile;

            if (! $profile && ! $this->dryRun) {
                $profile = $user->staffProfile()->create(['published_at' => null]);
                $this->bump('staff_profiles_created');
            }

            if ($profile) {
                $profiles[$user->getKey()] = $profile->getKey();
            }
        }

        return $profiles;
    }

    /**
     * The anamnesis field's contents: the dated entry, with the Kartotéka
     * description appended when the card had one — it can hold an allergy or
     * similar, which must not be lost to a later write-up.
     *
     * @param  array{date: Carbon, content: string}  $entry
     */
    protected function anamnesisHtml(array $entry, ?string $cardDescription = null): string
    {
        $html = (string) $this->textToHtml(
            $entry['content'],
            'Anamnéza z '.$entry['date']->format('j. n. Y'),
        );

        if ($cardDescription !== null) {
            $html .= (string) $this->textToHtml($cardDescription, 'Popis / indispozice z kartotéky');
        }

        return $html;
    }

    /**
     * Raw inserts on purpose: skips HasUuids/observer/audit events, which would
     * mean mention scanning + audit rows for thousands of historical notes.
     *
     * @param  list<array<string, mixed>>  $inserts
     */
    protected function insertNotes(array $inserts): void
    {
        foreach (array_chunk($inserts, 500) as $chunk) {
            ClientNote::query()->insert($chunk);
        }
    }

    /**
     * The client a dated note belongs to. Notes only carry a name, so where one
     * name covers several people they are told apart by an explicit override
     * first, then by which person was in treatment on that date.
     *
     * @param  list<array{user_id: ?string, emails: list<string>, start: ?Carbon, end: ?Carbon}>  $candidates
     */
    protected function matchClient(string $clientKey, Carbon $date, array $candidates): ?string
    {
        if ($candidates === []) {
            return null;
        }

        if (count($candidates) === 1) {
            return $candidates[0]['user_id'];
        }

        $override = self::NOTE_OVERRIDES[$clientKey.'|'.$date->toDateString()] ?? null;

        if ($override) {
            foreach ($candidates as $candidate) {
                if (in_array($override, $candidate['emails'], true)) {
                    $this->bump('notes_resolved_by_override');

                    return $candidate['user_id'];
                }
            }
        }

        $inTreatment = array_values(array_filter(
            $candidates,
            fn (array $candidate): bool => $candidate['start']
                && $date->betweenIncluded($candidate['start'], $candidate['end'] ?? $candidate['start']),
        ));

        if (count($inTreatment) === 1) {
            $this->bump('notes_resolved_by_date');

            return $inTreatment[0]['user_id'];
        }

        return null;
    }

    /**
     * @param  array<string, string>  $row
     * @return array{client: string, date: Carbon, content: string, signature_source?: string}|null
     */
    protected function mapTherapyRow(array $row): ?array
    {
        $client = $this->normalizeWhitespace(($row['Jméno'] ?? '').' '.($row['Přijmení'] ?? $row['Příjmení'] ?? ''));
        $date = $this->parseDate($row['Datum terapie'] ?? '', 'd/m/Y');
        $content = trim($row['Zápis z terapie'] ?? '');

        if ($client === '' || $date === null || $content === '') {
            return null;
        }

        // The therapist signs the note's last line, so the author must be
        // resolved from the note as written, before the referral is appended.
        $signatureSource = $content;

        if ($referral = $this->cleanValue($row['Doporučení ke specialistovi'] ?? '')) {
            $content .= "\n\nDoporučení ke specialistovi: {$referral}";
        }

        return [
            'client' => $client,
            'date' => $date,
            'content' => $content,
            'signature_source' => $signatureSource,
        ];
    }

    /**
     * @param  array<string, string>  $row
     * @return array{client: string, date: Carbon, content: string, heading?: string}|null
     */
    protected function mapOverviewRow(array $row): ?array
    {
        $client = $this->normalizeWhitespace(($row['Jméno'] ?? '').' '.($row['Příjmení'] ?? $row['Přijmení'] ?? ''));
        $date = $this->parseDate($row['Datum přehledu'] ?? '', 'd/m/Y');
        $content = trim($row['Komentář'] ?? '');

        $pains = [];

        for ($index = 1; $index <= 14; $index++) {
            $place = $this->cleanValue($row["Místo bolesti {$index}"] ?? '');

            if ($place === null) {
                continue;
            }

            $line = "Bolest: {$place}";

            if ($character = $this->cleanValue($row["Charakter bolesti {$index}"] ?? '')) {
                $line .= ", {$character}";
            }

            if ($intensity = $this->cleanValue($row["Intenzita bolesti {$index}"] ?? '')) {
                $line .= ", intenzita {$intensity}";
            }

            if ($description = $this->cleanValue($row["Popis bolesti {$index}"] ?? '')) {
                $line .= " — {$description}";
            }

            $pains[] = $line;
        }

        if ($pains !== []) {
            $content = trim($content."\n\n".implode("\n", $pains));
        }

        if ($client === '' || $date === null || $content === '') {
            return null;
        }

        return [
            'client' => $client,
            'date' => $date,
            'content' => $content,
        ];
    }

    /**
     * Existing note keys for deduplication, "client|timestamp|md5(content)".
     *
     * @return array<string, bool>
     */
    protected function existingNoteKeys(): array
    {
        $keys = [];

        foreach (DB::table('client_notes')->select('id', 'client_id', 'created_at', 'content')->cursor() as $note) {
            $timestamp = Carbon::parse($note->created_at)->format('Y-m-d H:i:s');
            $keys[$note->client_id.'|'.$timestamp.'|'.md5((string) $note->content)] = $note->id;
        }

        return $keys;
    }

    /**
     * The therapist who wrote a note, taken from the line they signed it with.
     * Signatures were hand-typed over two and a half years, so they turn up as
     * a full name, a bare surname, a first name, initials, or a misspelling of
     * any of those.
     *
     * @param  array{full: array<string, string>, surname: array<string, string>, first: array<string, string>}  $authors
     */
    protected function resolveSignature(string $content, array $authors): ?string
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $content))));

        if ($lines === []) {
            return null;
        }

        $lastLine = end($lines);
        $signature = $this->normalizeSignature($lastLine);

        if (isset($authors['full'][$signature])) {
            return $authors['full'][$signature];
        }

        // Only a short trailing line can be a signature; anything longer is the
        // note itself and must never be matched against a name.
        $words = preg_split('/[^\p{L}]+/u', $signature, flags: PREG_SPLIT_NO_EMPTY) ?: [];

        if (mb_strlen($signature) <= 40 && $words !== [] && count($words) <= 3) {
            foreach ($words as $word) {
                foreach (['surname', 'first'] as $index) {
                    if (isset($authors[$index][$word])) {
                        return $authors[$index][$word];
                    }
                }
            }

            // Misspellings ("Šárka Antošíkvá", "Mučová", "Zuzana Goldma") stay
            // close enough to a known name to be recovered by edit distance.
            foreach (['full', 'surname'] as $index) {
                foreach ($authors[$index] as $known => $authorId) {
                    if (levenshtein($signature, $known) <= 2) {
                        return $authorId;
                    }
                }
            }
        }

        // Anything name-shaped (2–3 capitalized words) that we can't attribute
        // is worth surfacing — it may be another former therapist.
        if (preg_match('/^\p{Lu}\p{Ll}+(?: \p{Lu}\p{Ll}+){1,2}$/u', $lastLine)) {
            $this->unmatchedSignatures[$lastLine] = ($this->unmatchedSignatures[$lastLine] ?? 0) + 1;
        }

        return null;
    }

    /**
     * @return iterable<int, array<string, string>>
     */
    protected function readCsv(string $file): iterable
    {
        $handle = fopen($file, 'r');

        if ($handle === false) {
            return;
        }

        $headers = null;

        while (($row = fgetcsv($handle, null, ';', '"', '\\')) !== false) {
            if ($headers === null) {
                // Strip a potential UTF-8 BOM from the first header cell.
                $row[0] = ltrim((string) $row[0], "\u{FEFF}");
                $headers = array_map(trim(...), $row);

                continue;
            }

            if (count($row) < count($headers)) {
                $row = array_pad($row, count($headers), '');
            }

            yield array_combine($headers, array_map(strval(...), array_slice($row, 0, count($headers))));
        }

        fclose($handle);
    }

    protected function normalizeWhitespace(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    protected function normalizeName(string $value): string
    {
        return mb_strtolower($this->normalizeWhitespace($value));
    }

    /**
     * Signature comparison key: lowercase and diacritics-free, so "Sarka
     * Antosikova" and "Šárka Antošíková" collapse to the same string.
     */
    protected function normalizeSignature(string $value): string
    {
        return Str::ascii($this->normalizeName($value));
    }

    protected function reverseName(string $normalized): string
    {
        return implode(' ', array_reverse(explode(' ', $normalized)));
    }

    protected function normalizeEmail(string $email, string $name): string
    {
        $email = mb_strtolower(trim($email));

        // Ergobody's placeholder addresses for clients with no known e-mail:
        // rewrite to our own so they are recognizable and never contacted.
        if (preg_match('/^info\+(\d+)@ergobody\.cz$/', $email, $matches)) {
            return "import+{$matches[1]}@friendlyfyzio.cz";
        }

        if ($email === '') {
            return 'import+'.Str::slug($name).'@friendlyfyzio.cz';
        }

        return $email;
    }

    protected function normalizePhone(string $phone): ?string
    {
        $phone = preg_replace('/\s+/', '', trim($phone)) ?? '';

        if ($phone === '') {
            return null;
        }

        if (preg_match('/^\d{9}$/', $phone)) {
            return "+420{$phone}";
        }

        return $phone;
    }

    protected function mapGender(string $value): ?Gender
    {
        return match (trim($value)) {
            'Žena' => Gender::Female,
            'Muž' => Gender::Male,
            'Jiné' => Gender::Other,
            default => null,
        };
    }

    protected function cleanValue(string $value): ?string
    {
        $value = $this->normalizeWhitespace($value);

        return in_array($value, ['', '-'], true) ? null : $value;
    }

    protected function parseMeasurement(string $value): ?float
    {
        $value = (float) str_replace(',', '.', trim($value));

        return $value > 0 ? $value : null;
    }

    protected function parseDate(string $value, string $format): ?Carbon
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return rescue(fn (): Carbon => Carbon::createFromFormat($format, $value)->startOfDay(), null, false);
    }

    /**
     * Plain CSV text as minimal rich-text HTML: paragraphs on blank lines,
     * line breaks within, everything escaped.
     */
    protected function textToHtml(string $text, ?string $heading = null): ?string
    {
        $text = trim(str_replace("\r\n", "\n", $text));

        if ($text === '' && $heading === null) {
            return null;
        }

        $paragraphs = preg_split('/\n{2,}/', $text) ?: [];

        $html = collect($paragraphs)
            ->map(fn (string $paragraph): string => '<p>'.nl2br(e(trim($paragraph)), false).'</p>')
            ->implode('');

        if ($heading !== null) {
            $html = '<p><strong>'.e($heading).'</strong></p>'.$html;
        }

        return $html;
    }

    protected function bump(string $key): void
    {
        $this->stats[$key] = ($this->stats[$key] ?? 0) + 1;
    }

    protected function printSummary(): void
    {
        $this->newLine();
        $this->info($this->dryRun ? 'Dry run summary:' : 'Import summary:');

        ksort($this->stats);

        $this->table(
            ['Metrika', 'Počet'],
            collect($this->stats)->map(fn (int $count, string $key): array => [$key, $count])->values()->all(),
        );

        if ($this->unmatchedClients !== []) {
            arsort($this->unmatchedClients);
            $this->warn('Notes without a matching imported client (name → notes):');

            foreach (array_slice($this->unmatchedClients, 0, 20, true) as $name => $count) {
                $this->line("  {$name}: {$count}");
            }
        }

        if ($this->unmatchedSignatures !== []) {
            arsort($this->unmatchedSignatures);
            $this->warn('Unattributed name-shaped signatures (possible former therapists):');

            foreach ($this->unmatchedSignatures as $name => $count) {
                $this->line("  {$name}: {$count}");
            }
        }
    }
}
