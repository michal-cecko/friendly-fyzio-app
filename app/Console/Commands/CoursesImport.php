<?php

namespace App\Console\Commands;

use App\Enums\Capability;
use App\Enums\CourseEnrollmentStatus;
use App\Enums\CourseSeriesStatus;
use App\Enums\CourseSeriesVisibility;
use App\Enums\OfferVisibility;
use App\Enums\PaymentStatus;
use App\Models\Building;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\EventCategory;
use App\Models\Lesson;
use App\Models\Room;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One-off import of the autumn-2025 group-course catalogue and the 2024–25
 * rosters from the clinic's Google Sheets, as **historical records only**.
 *
 * The whole autumn term has already ended (owner: "yes it ended; no current or
 * upcoming are seeded"), so every series is created `Inactive` and every event
 * lies in the past. `CourseArchive` only lists series with `end_date >= today`
 * and the workshopy page only future events, so none of this surfaces on the
 * public site — it exists for the admin history and each enrolled client's
 * account.
 *
 * ---------------------------------------------------------------------------
 * The rules below encode decisions taken with the owner. They are not derivable
 * from the sheets alone — do not "simplify" them.
 * ---------------------------------------------------------------------------
 *
 * Lecturers: the five existing staff who teach ({@see EXISTING_STAFF} names)
 * simply gain the Lecturer capability. Klára Čecháková and Anna Fančovičová keep
 * their existing **customer** identity (client zone) *and* gain Lecturer (owner
 * decision). The four otherwise-unknown lecturers are created as lecturer-only
 * staff with an unpublished profile, so they never appear on /o-nas. A "Mgr."
 * prefix moves to `title_before`; a two-name "A, B" lecturer cell records the
 * first as the instructor and both in the event description.
 *
 * Money & schedule: a course row's price is the whole-term price; a workshop's
 * two-tier price ("1 000 Kč / 1 800 Kč … za 1 blok / 2 bloky") is stored at its
 * *lower* tier with the full wording kept in the description. Lessons are
 * generated weekly from each schedule track's explicit start date (Mami&Mimi
 * runs two tracks, Wed + Thu), skipping the "9. října nebude" week where noted.
 *
 * Rosters: people are matched to clients by e-mail, then by a unique name; an
 * unknown holder gets a placeholder account exactly like the ergobody/voucher
 * imports. "Principy pohybu pro těhotné ženy" maps onto the catalogue series;
 * "Restart po císařském řezu" (absent from the catalogue) becomes its own
 * historical course; the unnamed left-hand roster block carries no course title
 * and no e-mails, so it is reported unmatched, never guessed onto a course.
 *
 * No e-mail can result from this import: series are created `Inactive` (the
 * only notifying transition is a series opening publicly), enrollments have no
 * created-event notification, and activity logging is disabled so the audit
 * trail is not flooded. Idempotent: courses key on slug, series on
 * (course, start date), lessons on (series, date, time), enrollments on
 * (series, client).
 */
class CoursesImport extends Command
{
    protected $signature = 'courses:import
        {path=export/googlesheets : Directory containing the course CSV exports}
        {--dry-run : Parse and report without writing anything}';

    protected $description = 'Importuje historické kurzy, workshopy a přihlášky z exportů Google Sheets (podzim 2025).';

    public const string IMPORT_TAG = 'Kurzy import';

    /** The autumn term all bare "d. m." dates fall in. */
    protected const int TERM_YEAR = 2025;

    /**
     * Existing staff accounts (by name) who also teach group courses: they gain
     * the Lecturer capability alongside whatever they already hold.
     *
     * @var array<string, string> lecturer name => staff login e-mail
     */
    protected const array EXISTING_STAFF = [
        'Denisa Nováková' => 'denisa.novakova@friendlyfyzio.cz',
        'Lucie Fickerová' => 'lucie.fickerova@friendlyfyzio.cz',
        'Kristýna Černá' => 'kristyna.cerna@friendlyfyzio.cz',
        'Ema Murčová' => 'ema.murcova@friendlyfyzio.cz',
    ];

    /**
     * External room-renters: they rent space for their own courses and are not
     * part of the practice. Their catalogue rows are skipped entirely — never
     * imported as courses/workshops and never given an account. All their rows
     * in the export are solo-taught, so matching the primary lecturer is safe.
     *
     * @var list<string>
     */
    protected const array EXTERNAL_RENTAL_LECTURERS = [
        'Lucie Amani',
    ];

    /**
     * Lecturers who are also clients of the practice (owner-confirmed): they
     * keep their customer identity and additionally gain Lecturer.
     *
     * @var list<string>
     */
    protected const array CUSTOMER_LECTURERS = [
        'Klára Čecháková',
        'Anna Fančovičová',
    ];

    /**
     * Czech month names → month number, for the "9. října nebude" skip note.
     *
     * @var array<string, int>
     */
    protected const array CZECH_MONTHS = [
        'ledna' => 1, 'února' => 2, 'března' => 3, 'dubna' => 4,
        'května' => 5, 'června' => 6, 'července' => 7, 'srpna' => 8,
        'září' => 9, 'října' => 10, 'listopadu' => 11, 'prosince' => 12,
    ];

    /** Academic-title prefixes lifted out of a name into `title_before`. */
    protected const array TITLE_PREFIXES = ['Mgr.', 'Bc.', 'MUDr.', 'MDDr.', 'PhDr.', 'Ing.', 'DiS.', 'Ph.D.'];

    /**
     * Roster block title → the course its people enroll into. `catalogue`
     * blocks reuse the series built from the catalogue; the rest describe a
     * course that only exists in the roster.
     *
     * @var array<string, array<string, mixed>>
     */
    protected const array ROSTER_BLOCKS = [
        'principy pohybu pro těhotné ženy' => [
            'catalogue' => true,
            'course_name' => 'Principy pohybu (naučně pohybové)',
            'start' => '2025-09-30',
        ],
        'restart po císařském řezu' => [
            'catalogue' => false,
            'course_name' => 'Restart po císařském řezu',
            'category' => 'Ostatní',
            'instructor' => 'Lucie Fickerová',
            'time' => '16:00',
            'start' => '11. 11.',
            'lessons' => 5,
            'capacity' => 7,
            'price' => 1750,
        ],
    ];

    protected bool $dryRun = false;

    /** @var array<string, int> */
    protected array $stats = [];

    /** @var list<string> */
    protected array $warnings = [];

    /**
     * Resolved lecturer accounts, keyed by normalized name.
     *
     * @var array<string, User>
     */
    protected array $lecturers = [];

    /**
     * Series created from the catalogue, keyed by "courseSlug|Y-m-d start".
     *
     * @var array<string, CourseSeries>
     */
    protected array $seriesBySignature = [];

    /** @var array<string, CourseCategory> */
    protected array $categories = [];

    protected ?Room $room = null;

    /** @var array<string, string>|null email => user id */
    protected ?array $clientsByEmail = null;

    /** @var array<string, string>|null normalized name => user id (unique names only) */
    protected ?array $clientsByName = null;

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        $directory = $this->argument('path');

        if (! str_starts_with((string) $directory, '/')) {
            $directory = base_path($directory);
        }

        $catalogue = $this->findCsv($directory, 'Seznam');
        $rosters = $this->findCsv($directory, 'Kurzy Lucka');

        if (! $catalogue) {
            $this->error("Course catalogue (…Seznam.csv) not found in {$directory}.");

            return self::FAILURE;
        }

        if ($this->dryRun) {
            $this->warn('Dry run — no changes will be written.');
        }

        // Bulk import must not flood the audit trail with thousands of rows.
        activity()->disableLogging();

        [$recurring, $workshops] = $this->parseCatalogue($catalogue);

        $this->resolveLecturers($recurring, $workshops);
        $this->importRecurring($recurring);
        $this->importWorkshops($workshops);

        if ($rosters) {
            $this->importRosters($rosters);
        } else {
            $this->warn('Roster export (…Kurzy Lucka.csv) not found — enrollments skipped.');
        }

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
     * Split the catalogue sheet into its recurring-course rows (Pro těhotné +
     * Ostatní) and its workshop rows, tracking the current section header.
     *
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    protected function parseCatalogue(string $file): array
    {
        $recurring = [];
        $workshops = [];
        $section = null;

        foreach ($this->readRows($file) as $cells) {
            $first = $this->cell($cells, 1);

            if ($first === '' && $this->cell($cells, 2) === '') {
                continue;
            }

            if (in_array($first, ['Pro těhotné', 'Ostatní', 'Workshopy'], true)) {
                $section = $first;

                continue;
            }

            if ($first === 'Název') {
                continue; // per-section column header
            }

            if ($section === null) {
                continue;
            }

            if ($section === 'Workshopy') {
                $workshops[] = $this->mapWorkshopRow($cells);
            } else {
                $recurring[] = $this->mapRecurringRow($cells, $section);
            }
        }

        return [
            array_values(array_filter($recurring)),
            array_values(array_filter($workshops)),
        ];
    }

    /**
     * @param  list<string>  $cells
     * @return array<string, mixed>|null
     */
    protected function mapRecurringRow(array $cells, string $section): ?array
    {
        $name = $this->cleanName($this->cell($cells, 1));

        if ($name === '') {
            return null;
        }

        if ($this->isExternalRentalLecturer($this->cell($cells, 8))) {
            $this->bump('rows_skipped_rental');

            return null;
        }

        return [
            'name' => $name,
            'category' => $section,
            'times' => $this->splitList($this->cell($cells, 3)),
            'starts' => $this->splitList($this->cell($cells, 4)),
            'lessons' => (int) $this->cell($cells, 5),
            'capacity' => (int) $this->cell($cells, 6),
            'price' => $this->parsePrice($this->cell($cells, 7)),
            'lecturer' => $this->cell($cells, 8),
            'skip' => $this->parseSkipDate($this->cell($cells, 10)),
        ];
    }

    /**
     * @param  list<string>  $cells
     * @return array<string, mixed>|null
     */
    protected function mapWorkshopRow(array $cells): ?array
    {
        $name = $this->cleanName($this->cell($cells, 1));

        if ($name === '') {
            return null;
        }

        if ($this->isExternalRentalLecturer($this->cell($cells, 8))) {
            $this->bump('rows_skipped_rental');

            return null;
        }

        $priceText = $this->cell($cells, 7);
        $priceNote = $this->cell($cells, 10);

        return [
            'name' => $name,
            'date' => $this->parseCzDate($this->cell($cells, 2)),
            'time' => $this->cell($cells, 3),
            'capacity' => (int) $this->cell($cells, 6),
            'price' => $this->parsePrice($priceText),
            'price_text' => trim($priceText),
            'price_note' => trim($priceNote),
            'lecturer' => $this->cell($cells, 8),
        ];
    }

    /**
     * Resolve or create every lecturer named in the catalogue, granting the
     * Lecturer capability. Existing staff and client-lecturers keep whatever
     * they already are; unknown names become lecturer-only staff.
     *
     * @param  list<array<string, mixed>>  $recurring
     * @param  list<array<string, mixed>>  $workshops
     */
    protected function resolveLecturers(array $recurring, array $workshops): void
    {
        $names = [];

        foreach ([...$recurring, ...$workshops] as $row) {
            foreach ($this->splitLecturers((string) $row['lecturer']) as $lecturer) {
                $names[$this->normalizeName($lecturer['name'])] = $lecturer;
            }
        }

        // "Restart po císařském řezu" is roster-only, but its lecturer must exist.
        foreach (self::ROSTER_BLOCKS as $block) {
            if (isset($block['instructor'])) {
                $lecturer = $this->splitTitle((string) $block['instructor']);
                $names[$this->normalizeName($lecturer['name'])] = $lecturer;
            }
        }

        foreach ($names as $key => $lecturer) {
            $this->lecturers[$key] = $this->resolveLecturer($lecturer['name'], $lecturer['title_before']);
        }
    }

    protected function resolveLecturer(string $name, ?string $titleBefore): User
    {
        $normalized = $this->normalizeName($name);

        if ($existing = $this->lecturers[$normalized] ?? null) {
            return $existing;
        }

        $isCustomerLecturer = in_array($name, self::CUSTOMER_LECTURERS, true);
        $user = $this->findUserForLecturer($name);

        if ($user === null) {
            $this->bump($isCustomerLecturer ? 'lecturers_customer_created' : 'lecturers_created');

            if ($this->dryRun) {
                return new User(['name' => $name]);
            }

            $user = new User;
            $user->fill(['name' => $name, 'title_before' => $titleBefore, 'email' => $this->lecturerEmail($name)]);
            $user->forceFill(['password' => Str::password(40)]);
            $user->save();

            if ($isCustomerLecturer) {
                $user->markAsCustomer();
                $user->attachTag(self::IMPORT_TAG);
            }
        } else {
            $this->bump('lecturers_matched');

            if (! $this->dryRun && $titleBefore && blank($user->title_before)) {
                $user->forceFill(['title_before' => $titleBefore])->save();
            }
        }

        if (! $this->dryRun) {
            // Idempotent; also auto-creates an unpublished staff profile for a
            // brand-new lecturer, so they never appear on the public team page.
            $user->grantCapability(Capability::Lecturer);
        }

        return $user;
    }

    /**
     * A staff-style login for a brand-new lecturer, diacritics-stripped and
     * deduplicated against the unique users.email column.
     */
    protected function lecturerEmail(string $name): string
    {
        $base = Str::of($name)->ascii()->lower()->replaceMatches('/[^a-z]+/', '.')->trim('.')->value() ?: 'lektor';
        $email = $base.'@friendlyfyzio.cz';

        for ($suffix = 2; User::query()->where('email', $email)->exists(); $suffix++) {
            $email = $base.'-'.$suffix.'@friendlyfyzio.cz';
        }

        return $email;
    }

    /**
     * The account a catalogue lecturer belongs to: an existing staff login, a
     * uniquely-named existing user (for the client-lecturers), else none.
     */
    protected function findUserForLecturer(string $name): ?User
    {
        if ($email = self::EXISTING_STAFF[$name] ?? null) {
            return User::query()->where('email', $email)->first();
        }

        $matches = User::query()->whereRaw('LOWER(name) = ?', [$this->normalizeName($name)])->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    protected function lecturerFor(string $lecturerCell): ?User
    {
        $primary = $this->splitLecturers($lecturerCell)[0] ?? null;

        if ($primary === null) {
            return null;
        }

        return $this->lecturers[$this->normalizeName($primary['name'])] ?? null;
    }

    /**
     * @param  list<array<string, mixed>>  $recurring
     */
    protected function importRecurring(array $recurring): void
    {
        // A course name shared by several lecturers needs a lecturer-qualified
        // slug so the two runs don't collide on the unique slug.
        $instructorsByName = [];

        foreach ($recurring as $row) {
            $name = (string) $row['name'];
            $instructorsByName[$name][$this->normalizeName($this->splitTitle((string) $row['lecturer'])['name'])] = true;
        }

        foreach ($recurring as $row) {
            $instructor = $this->lecturerFor((string) $row['lecturer']);

            if ($instructor === null) {
                $this->warnings[] = "No lecturer for course “{$row['name']}” ({$row['lecturer']}).";
                $this->bump('courses_skipped_no_lecturer');

                continue;
            }

            $shared = count($instructorsByName[(string) $row['name']] ?? []) > 1;
            $course = $this->ensureCourse((string) $row['name'], (string) $row['category'], $instructor, $shared);

            $this->buildSeries($course, $instructor, $row);
        }
    }

    protected function ensureCourse(string $name, string $categoryName, User $instructor, bool $lecturerQualifiedSlug): Course
    {
        $slug = Str::slug($name);

        if ($lecturerQualifiedSlug) {
            $slug .= '-'.Str::slug(Str::afterLast($instructor->name, ' '));
        }

        if ($this->dryRun) {
            return new Course(['name' => $name, 'slug' => $slug]);
        }

        $category = $this->ensureCategory($categoryName);

        $course = Course::query()->firstOrNew(['slug' => $slug]);

        if (! $course->exists) {
            $this->bump('courses_created');
        }

        $course->fill([
            'category_id' => $category->getKey(),
            'instructor_id' => $instructor->getKey(),
            'name' => $name,
            // Historical courses stay unpublished: the whole autumn term has
            // ended, so they must not surface on the public archive (neither the
            // grid nor the "Připravujeme" tail). A human can publish one later to
            // reuse it as a template — preserve that choice on re-run.
            'published_at' => $course->exists ? $course->published_at : null,
        ]);
        $course->save();

        return $course;
    }

    /**
     * Create one historical series for a catalogue row and generate its lessons.
     *
     * @param  array<string, mixed>  $row
     */
    protected function buildSeries(Course $course, User $instructor, array $row): void
    {
        $tracks = $this->scheduleTracks($row['starts'], $row['times']);

        if ($tracks === []) {
            $this->warnings[] = "No schedule for course “{$row['name']}”.";
            $this->bump('series_skipped_no_schedule');

            return;
        }

        $duration = $this->durationFor((string) $row['name']);
        $dates = $this->lessonDates($tracks, (int) $row['lessons'], $row['skip']);
        $startDate = $tracks[0]['start'];
        $endDate = $dates === [] ? $startDate : end($dates)['date'];

        $label = $this->scheduleLabel($tracks);
        $seriesName = 'Podzim 2025 · '.$label;
        $signature = $course->slug.'|'.$startDate->toDateString();

        // Keyed by (course, name): same-day parallel offerings differ only by
        // their weekday/time, which the name — not the start date — carries.
        $series = $course->exists
            ? CourseSeries::query()->where('course_id', $course->getKey())->where('name', $seriesName)->first()
            : null;

        $this->bump($series === null ? 'series_created' : 'series_existing');

        if ($this->dryRun) {
            // Register an unsaved series so roster enrollments still resolve and
            // the dry-run reports the real enrollment counts.
            $this->seriesBySignature[$signature] = $series ?? new CourseSeries(['start_date' => $startDate->toDateString()]);
            $this->bump('lessons_created', count($dates));

            return;
        }

        $series ??= new CourseSeries(['course_id' => $course->getKey()]);

        $series->fill([
            'start_date' => $startDate->toDateString(),
            'name' => $seriesName,
            'end_date' => $endDate->toDateString(),
            'capacity' => (int) $row['capacity'],
            'price' => (int) $row['price'],
            'status' => CourseSeriesStatus::Inactive,
            'visibility' => CourseSeriesVisibility::Public,
        ]);
        $series->save();

        $this->seriesBySignature[$signature] = $series;

        $this->createLessons($series, $instructor, $dates, $duration);
    }

    /**
     * @param  list<array{date: Carbon, time: string}>  $dates
     */
    protected function createLessons(CourseSeries $series, User $instructor, array $dates, int $duration): void
    {
        $room = $this->defaultRoom();

        foreach ($dates as $slot) {
            $start = $slot['time'];
            $end = Carbon::parse($start)->addMinutes($duration)->format('H:i:s');

            $exists = Lesson::query()
                ->where('series_id', $series->getKey())
                ->whereDate('lesson_date', $slot['date']->toDateString())
                ->where('start_time', $start)
                ->exists();

            if ($exists) {
                continue;
            }

            Lesson::query()->create([
                'series_id' => $series->getKey(),
                'instructor_id' => $instructor->getKey(),
                'room_id' => $room->getKey(),
                'lesson_date' => $slot['date']->toDateString(),
                'start_time' => $start,
                'end_time' => $end,
            ]);

            $this->bump('lessons_created');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $workshops
     */
    protected function importWorkshops(array $workshops): void
    {
        $category = $this->dryRun ? null : $this->ensureEventCategory();

        foreach ($workshops as $row) {
            $instructor = $this->lecturerFor((string) $row['lecturer']);

            if ($instructor === null || $row['date'] === null) {
                $this->warnings[] = "Workshop “{$row['name']}” skipped (lecturer/date missing).";
                $this->bump('workshops_skipped');

                continue;
            }

            [$start, $end] = $this->parseTimeRange((string) $row['time']);
            $slug = Str::slug($row['name']).'-'.$row['date']->format('Y-m-d');

            $event = $this->dryRun ? null : Lesson::query()->firstOrNew(['slug' => $slug]);

            $this->bump($event?->exists ? 'workshops_existing' : 'workshops_created');

            if ($this->dryRun) {
                continue;
            }

            if (! $event->exists) {
                $event->fill([
                    'event_category_id' => $category->getKey(),
                    'instructor_id' => $instructor->getKey(),
                    'room_id' => $this->defaultRoom()->getKey(),
                    'visibility' => OfferVisibility::Public,
                    'name' => $row['name'],
                    'slug' => $slug,
                    'description' => $this->workshopDescription($row),
                    'lesson_date' => $row['date']->toDateString(),
                    'start_time' => $start,
                    'end_time' => $end,
                    'capacity' => (int) $row['capacity'],
                    'price' => (int) $row['price'],
                    // Past workshop: unpublished so it stays off the public
                    // archive (including its "proběhlé akce" tail).
                    'published_at' => null,
                ]);
                $event->save();
            }
        }
    }

    /**
     * The public description keeps the two-tier price wording and any
     * co-lecturer, which the single price/instructor columns can't hold.
     *
     * @param  array<string, mixed>  $row
     */
    protected function workshopDescription(array $row): ?string
    {
        $lines = [];
        $lecturers = $this->splitLecturers((string) $row['lecturer']);

        if (count($lecturers) > 1) {
            $lines[] = 'Lektorky: '.implode(', ', array_map(fn (array $l): string => trim(($l['title_before'] ? $l['title_before'].' ' : '').$l['name']), $lecturers));
        }

        $priceLine = trim((string) $row['price_text']);

        if ($row['price_note'] !== '') {
            $priceLine .= ' ('.$row['price_note'].')';
        }

        if ($priceLine !== '') {
            $lines[] = 'Cena: '.$priceLine;
        }

        if ($lines === []) {
            return null;
        }

        return collect($lines)->map(fn (string $line): string => '<p>'.e($line).'</p>')->implode('');
    }

    /**
     * Import the rosters as enrollments against the mapped series.
     */
    protected function importRosters(string $file): void
    {
        $blocks = $this->parseRosters($file);

        foreach ($blocks as $title => $people) {
            $normalized = $this->normalizeName($title);

            if ($title === '__unnamed__' || ! isset(self::ROSTER_BLOCKS[$normalized])) {
                foreach ($people as $person) {
                    $this->warnings[] = 'Unmatched roster entry (no course): '.$person['name'];
                    $this->bump('enrollments_unmatched');
                }

                continue;
            }

            $series = $this->seriesForRosterBlock(self::ROSTER_BLOCKS[$normalized]);

            if ($series === null) {
                foreach ($people as $person) {
                    $this->bump('enrollments_unmatched');
                }

                continue;
            }

            foreach ($people as $person) {
                $this->enroll($series, $person);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $block
     */
    protected function seriesForRosterBlock(array $block): ?CourseSeries
    {
        if ($block['catalogue'] === true) {
            $slug = Str::slug((string) $block['course_name']);
            $signature = $slug.'|'.$block['start'];
            $series = $this->seriesBySignature[$signature] ?? null;

            if ($series === null && ! $this->dryRun) {
                $series = CourseSeries::query()
                    ->whereHas('course', fn ($q) => $q->where('slug', $slug))
                    ->where('start_date', $block['start'])
                    ->first();
            }

            return $series;
        }

        // Roster-only course (Restart po císařském řezu): build it once.
        return $this->buildRosterOnlyCourse($block);
    }

    /**
     * @param  array<string, mixed>  $block
     */
    protected function buildRosterOnlyCourse(array $block): ?CourseSeries
    {
        $instructor = $this->lecturers[$this->normalizeName((string) $block['instructor'])] ?? null;

        if ($instructor === null) {
            return null;
        }

        $row = [
            'name' => $block['course_name'],
            'starts' => [(string) $block['start']],
            'times' => [(string) $block['time']],
            'lessons' => (int) $block['lessons'],
            'capacity' => (int) $block['capacity'],
            'price' => (int) $block['price'],
            'skip' => null,
        ];

        $course = $this->ensureCourse((string) $block['course_name'], (string) $block['category'], $instructor, false);
        $this->buildSeries($course, $instructor, $row);

        return $this->seriesBySignature[$course->slug.'|'.$this->parseCzDate((string) $block['start'])->toDateString()] ?? null;
    }

    /**
     * @param  array{name: string, email: ?string}  $person
     */
    protected function enroll(CourseSeries $series, array $person): void
    {
        $client = $this->resolveClient($person['name'], $person['email']);

        if ($client === null) {
            $this->warnings[] = 'Unmatched roster entry (no client): '.$person['name'];
            $this->bump('enrollments_unmatched');

            return;
        }

        if ($this->dryRun) {
            $this->bump('enrollments_created');

            return;
        }

        $enrollment = CourseEnrollment::query()->firstOrNew([
            'series_id' => $series->getKey(),
            'client_id' => $client->getKey(),
        ]);

        if ($enrollment->exists) {
            $this->bump('enrollments_existing');

            return;
        }

        $enrollment->fill([
            'status' => CourseEnrollmentStatus::Active,
            'payment_status' => PaymentStatus::Unpaid,
            'paid_at' => null,
        ])->save();

        $this->bump('enrollments_created');
    }

    /**
     * The client behind a roster entry: matched by e-mail, then by a unique
     * name; an unknown holder with an e-mail gets a placeholder account. A
     * nameless-and-e-mail-less entry (the unnamed block) resolves to nobody.
     */
    protected function resolveClient(string $name, ?string $email): ?User
    {
        $this->clientsByEmail ??= User::query()->select(['id', 'email'])->get()
            ->mapWithKeys(fn (User $u): array => [mb_strtolower((string) $u->email) => $u->getKey()])->all();

        if ($email !== null && ($id = $this->clientsByEmail[mb_strtolower($email)] ?? null)) {
            return User::query()->find($id);
        }

        $this->clientsByName ??= $this->uniqueNameIndex();
        $byName = $this->clientsByName[$this->normalizeName($name)] ?? null;

        if ($byName !== null && $email === null) {
            return User::query()->find($byName);
        }

        if ($email === null) {
            return null; // no e-mail and no unique name — never guessed
        }

        if ($this->dryRun) {
            $this->bump('clients_created');

            return new User(['name' => $name, 'email' => $email]);
        }

        $client = new User;
        $client->fill(['name' => $name, 'email' => mb_strtolower($email)]);
        $client->forceFill(['password' => Str::password(40)]);
        $client->save();
        $client->markAsCustomer();
        $client->attachTag(self::IMPORT_TAG);

        $this->clientsByEmail[mb_strtolower($email)] = $client->getKey();
        $this->bump('clients_created');

        return $client;
    }

    /**
     * @return array<string, string> normalized name => user id, unique names only
     */
    protected function uniqueNameIndex(): array
    {
        $counts = [];
        $index = [];

        foreach (User::query()->select(['id', 'name'])->cursor() as $user) {
            $key = $this->normalizeName((string) $user->name);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
            $index[$key] = $user->getKey();
        }

        return array_filter($index, fn (string $key): bool => $counts[$key] === 1, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Parse the two-column roster sheet into blocks of people, keyed by the
     * block's course title (or "__unnamed__" for the title-less left block).
     *
     * The sheet stacks two independent column groups — a left group at cells
     * 1–5 and a right group at 7–11 — each carrying one or more titled blocks
     * of (name, e-mail) rows preceded by a "Jméno" header. A non-label cell is
     * a title when a metadata/"Jméno" label follows it, otherwise it is a
     * person belonging to the current block.
     *
     * @return array<string, list<array{name: string, email: ?string}>>
     */
    protected function parseRosters(string $file): array
    {
        $rows = iterator_to_array($this->readRows($file));
        $blocks = [];

        foreach ([[1, 2], [7, 8]] as [$nameCol, $emailCol]) {
            $title = null;

            foreach ($rows as $index => $cells) {
                $value = $this->cell($cells, $nameCol);

                if ($value === '' || $this->isRosterLabel($value)) {
                    continue;
                }

                if ($this->rowIsTitle($rows, $index, $nameCol)) {
                    $title = $value;

                    continue;
                }

                $key = $title === null ? '__unnamed__' : $title;
                $blocks[$key][] = [
                    'name' => $this->normalizeWhitespace($value),
                    'email' => $this->cleanEmail($this->cell($cells, $emailCol)),
                ];
            }
        }

        return $blocks;
    }

    /**
     * A non-label cell heads a block when the next non-empty cell in its column
     * is a metadata/"Jméno" label; if the next non-empty cell is another plain
     * value it (and this one) are people in the current block.
     *
     * @param  list<list<string>>  $rows
     */
    protected function rowIsTitle(array $rows, int $index, int $col): bool
    {
        $count = count($rows);

        for ($next = $index + 1; $next < $count; $next++) {
            $value = $this->cell($rows[$next], $col);

            if ($value === '') {
                continue;
            }

            return $this->isRosterLabel($value);
        }

        return false;
    }

    protected function isRosterLabel(string $value): bool
    {
        return in_array($value, [
            'Začátek kurzu', 'Čas lekce', 'Počet lekcí', 'Cena za lekci',
            'Lektor', 'Počet náhrad', 'Počet lidí', 'Jméno',
        ], true);
    }

    // ---------------------------------------------------------------------
    // Schedule + value parsing
    // ---------------------------------------------------------------------

    /**
     * @param  list<string>  $starts
     * @param  list<string>  $times
     * @return list<array{start: Carbon, time: string}>
     */
    protected function scheduleTracks(array $starts, array $times): array
    {
        $tracks = [];
        $count = max(count($starts), count($times));

        for ($i = 0; $i < $count; $i++) {
            $start = $this->parseCzDate($starts[$i] ?? $starts[0] ?? '');
            $time = $this->parseTime($times[$i] ?? $times[0] ?? '');

            if ($start === null || $time === null) {
                continue;
            }

            $tracks[] = ['start' => $start, 'time' => $time];
        }

        return $tracks;
    }

    /**
     * Weekly lesson dates across every schedule track (round-robin per week),
     * skipping the noted "nebude" date, until `count` lessons are placed.
     *
     * @param  list<array{start: Carbon, time: string}>  $tracks
     * @return list<array{date: Carbon, time: string}>
     */
    protected function lessonDates(array $tracks, int $count, ?Carbon $skip): array
    {
        $dates = [];
        $week = 0;

        while (count($dates) < $count && $week < 60) {
            foreach ($tracks as $track) {
                $date = $track['start']->copy()->addWeeks($week);

                if ($skip !== null && $date->isSameDay($skip)) {
                    continue;
                }

                $dates[] = ['date' => $date, 'time' => $track['time']];

                if (count($dates) >= $count) {
                    break;
                }
            }

            $week++;
        }

        usort($dates, fn (array $a, array $b): int => $a['date'] <=> $b['date']);

        return $dates;
    }

    /**
     * @param  list<array{start: Carbon, time: string}>  $tracks
     */
    protected function scheduleLabel(array $tracks): string
    {
        return collect($tracks)
            ->map(fn (array $t): string => $this->czechWeekday($t['start']).' '.Carbon::parse($t['time'])->format('G:i'))
            ->implode(', ');
    }

    protected function durationFor(string $name): int
    {
        if (preg_match('/\((\d+)\s*minut/u', $name, $m)) {
            return (int) $m[1];
        }

        return 60;
    }

    /**
     * @return array{0: string, 1: string} start, end time
     */
    protected function parseTimeRange(string $value): array
    {
        $parts = preg_split('/[–-]/u', $value) ?: [];
        $start = $this->parseTime($parts[0] ?? '') ?? '00:00:00';
        $end = $this->parseTime($parts[1] ?? '') ?? Carbon::parse($start)->addHours(3)->format('H:i:s');

        return [$start, $end];
    }

    protected function parseTime(string $value): ?string
    {
        if (preg_match('/(\d{1,2}):(\d{2})/', trim($value), $m)) {
            return sprintf('%02d:%02d:00', (int) $m[1], (int) $m[2]);
        }

        return null;
    }

    protected function parseCzDate(string $value): ?Carbon
    {
        if (preg_match('/(\d{1,2})\.\s*(\d{1,2})\./u', trim($value), $m)) {
            return Carbon::create(self::TERM_YEAR, (int) $m[2], (int) $m[1])->startOfDay();
        }

        return null;
    }

    protected function parseSkipDate(string $note): ?Carbon
    {
        if (preg_match('/(\d{1,2})\.\s*([\p{L}]+)/u', trim($note), $m)) {
            $month = self::CZECH_MONTHS[mb_strtolower($m[2])] ?? null;

            if ($month !== null) {
                return Carbon::create(self::TERM_YEAR, $month, (int) $m[1])->startOfDay();
            }
        }

        return null;
    }

    protected function parsePrice(string $value): int
    {
        if (preg_match('/\d[\d\s\x{00A0}]*/u', $value, $m)) {
            return (int) preg_replace('/\D/u', '', $m[0]);
        }

        return 0;
    }

    protected function czechWeekday(Carbon $date): string
    {
        return [
            Carbon::MONDAY => 'pondělí', Carbon::TUESDAY => 'úterý',
            Carbon::WEDNESDAY => 'středa', Carbon::THURSDAY => 'čtvrtek',
            Carbon::FRIDAY => 'pátek', Carbon::SATURDAY => 'sobota',
            Carbon::SUNDAY => 'neděle',
        ][$date->dayOfWeek];
    }

    /**
     * @return list<string>
     */
    protected function splitList(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value)), fn (string $v): bool => $v !== ''));
    }

    /**
     * Split a lecturer cell into its people, each with any academic title
     * lifted out. "Anna Fančovičová, Lucie Fickerová" → two entries.
     *
     * @return list<array{name: string, title_before: ?string}>
     */
    protected function splitLecturers(string $value): array
    {
        return array_values(array_filter(
            array_map(fn (string $name): array => $this->splitTitle($name), $this->splitList($value)),
            fn (array $l): bool => $l['name'] !== '',
        ));
    }

    /**
     * Whether a catalogue row's lecturer cell belongs to an external room-renter
     * ({@see EXTERNAL_RENTAL_LECTURERS}): such rows are skipped entirely. Matches
     * on the primary (first) lecturer — every rental row is solo-taught.
     */
    protected function isExternalRentalLecturer(string $lecturerCell): bool
    {
        $primary = $this->splitLecturers($lecturerCell)[0]['name'] ?? '';

        if ($primary === '') {
            return false;
        }

        $rentals = array_map(fn (string $name): string => $this->normalizeName($name), self::EXTERNAL_RENTAL_LECTURERS);

        return in_array($this->normalizeName($primary), $rentals, true);
    }

    /**
     * @return array{name: string, title_before: ?string}
     */
    protected function splitTitle(string $value): array
    {
        $value = $this->normalizeWhitespace($value);
        $titles = [];
        $parts = explode(' ', $value);

        while ($parts !== [] && in_array($parts[0], self::TITLE_PREFIXES, true)) {
            $titles[] = array_shift($parts);
        }

        return [
            'name' => implode(' ', $parts),
            'title_before' => $titles === [] ? null : implode(' ', $titles),
        ];
    }

    protected function cleanName(string $value): string
    {
        return $this->normalizeWhitespace($value);
    }

    protected function cleanEmail(string $value): ?string
    {
        $value = mb_strtolower(trim($value));

        return $value === '' ? null : $value;
    }

    protected function ensureCategory(string $name): CourseCategory
    {
        return $this->categories[$name] ??= CourseCategory::query()->firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name, 'published_at' => now()],
        );
    }

    protected function ensureEventCategory(): EventCategory
    {
        return EventCategory::query()->firstOrCreate(
            ['slug' => 'workshopy'],
            ['name' => 'Workshopy', 'display_order' => 1, 'published_at' => now()],
        );
    }

    /**
     * The room historical lessons are filed under. The sheets don't record it;
     * the clinic's large gym is the sensible default, and the schema requires a
     * room, so one is resolved (or a minimal placeholder created).
     */
    protected function defaultRoom(): Room
    {
        return $this->room ??= Room::query()->where('name', 'Tělocvična velká')->first()
            ?? Room::query()->orderBy('created_at')->first()
            ?? $this->createPlaceholderRoom();
    }

    protected function createPlaceholderRoom(): Room
    {
        $building = Building::query()->firstOrCreate(
            ['name' => 'Hlavní budova'],
            ['address' => 'Zednická 1109/2, Ostrava-Poruba'],
        );

        return Room::query()->firstOrCreate(
            ['building_id' => $building->getKey(), 'name' => 'Tělocvična velká'],
            ['short_name' => 'TV'],
        );
    }

    protected function normalizeWhitespace(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $value)) ?? '');
    }

    protected function normalizeName(string $value): string
    {
        return mb_strtolower($this->normalizeWhitespace($value));
    }

    /**
     * @param  list<string>  $cells
     */
    protected function cell(array $cells, int $index): string
    {
        return $this->normalizeWhitespace($cells[$index] ?? '');
    }

    /**
     * @return iterable<int, list<string>>
     */
    protected function readRows(string $file): iterable
    {
        $handle = fopen($file, 'r');

        if ($handle === false) {
            return;
        }

        $first = true;

        while (($row = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            if ($first) {
                $row[0] = ltrim((string) ($row[0] ?? ''), "\u{FEFF}");
                $first = false;
            }

            yield array_map(strval(...), $row);
        }

        fclose($handle);
    }

    protected function bump(string $key, int $by = 1): void
    {
        $this->stats[$key] = ($this->stats[$key] ?? 0) + $by;
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

        if ($this->warnings !== []) {
            $this->warn('Notes:');

            foreach (array_slice($this->warnings, 0, 40) as $message) {
                $this->line('  '.$message);
            }

            if (count($this->warnings) > 40) {
                $this->line('  … +'.(count($this->warnings) - 40).' more');
            }
        }
    }
}
