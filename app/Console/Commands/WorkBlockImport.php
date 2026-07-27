<?php

namespace App\Console\Commands;

use App\Models\Room;
use App\Models\RoomBlocking;
use App\Models\StaffProfile;
use App\Models\TherapistWorkBlock;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Imports therapist work blocks from the "FRIENDLY FYZIO - ambulance" Google
 * Calendar ("obsazenost ambulancí"). Each event is a therapist occupying an
 * ambulance room for a stretch of the day — exactly a {@see TherapistWorkBlock}
 * (availability), which is what makes them bookable in the public wizard.
 *
 * The calendar is read through the Google Calendar API (the shared calendar
 * can't be exported to .ics) and dumped verbatim to a JSON snapshot this
 * command parses, so the mapping logic stays testable and the run is
 * deterministic/re-runnable. Each JSON entry is one *already-expanded* event
 * instance: `{ "summary": "...", "start": ISO8601, "end": ISO8601 }`.
 *
 * ---------------------------------------------------------------------------
 * Title conventions and owner decisions encoded below — not derivable from the
 * data alone, do not "simplify".
 * ---------------------------------------------------------------------------
 *
 * Titles read `{Therapist} - {ROOM}[ - note]`, hand-typed for years, so:
 *  - Room token is AV / VA (→ Ambulance velká) or AM (→ Ambulance malá); "VA"
 *    is just "AV" reversed. Trailing notes ("- od 1.9") are ignored.
 *  - Therapists appear by nickname ({@see NICKNAMES}); a nickname that maps to
 *    nobody is reported, never guessed onto a therapist.
 *  - **Rentals become room blockings, not availability** (owner): "Kuba" and
 *    "Lucka A." (Lucie Amani, now a lecturer) only rent a room, and anything
 *    marked "pronájem" is a rental too. They are not bookable therapists, so
 *    their time is no one's availability — but the room really is occupied, so
 *    each instance is written as a one-off {@see RoomBlocking}. Skipping them
 *    outright (as this command first did) left the room reading as free, which
 *    is how a client could be booked on top of a paying tenant.
 *  - **laser/kryo-tagged blocks are skipped**: that is device time (přístrojová
 *    terapie, phone-booked), not bookable physiotherapy availability. The
 *    Laser+Kryo calendar is imported separately as reservations.
 *
 * Past-dated instances are skipped (they add no future availability). No e-mail
 * can result: work blocks fire no notifications, and creation is a plain write.
 * Idempotent: a block is keyed by (therapist, date, start time).
 */
class WorkBlockImport extends Command
{
    protected $signature = 'work-blocks:import
        {path=export/googlecalendar/ambulance.json : JSON snapshot of the ambulance calendar}
        {--dry-run : Parse and report without writing anything}';

    protected $description = 'Importuje pracovní bloky terapeutů z Google kalendáře ambulancí.';

    /** Calendar wall-clock timezone; every instant is resolved into it. */
    protected const string TIMEZONE = 'Europe/Prague';

    /** Value marking a title token as a room rental rather than a therapist. */
    protected const string RENTAL = '__rental__';

    /**
     * Ordered nickname patterns → the therapist's login e-mail, or {@see RENTAL}
     * for room renters who are not bookable therapists. Titles are hand-typed in
     * any order ("Lada AV", "AV - Šárka", "Lucka - AV"), so each pattern is
     * matched as a whole word anywhere in the title. Order matters, most
     * specific first: "lucka a" (Amani, a lecturer who only rents) and "lucka f"
     * must be tested before a bare "lucka", which is Lucie Fickerová (the only
     * other Lucka, and the one who actually practises). Owner-confirmed.
     *
     * @var array<string, string>
     */
    protected const array NICKNAME_PATTERNS = [
        'lucka a' => self::RENTAL,
        'lucka f' => 'lucie.fickerova@friendlyfyzio.cz',
        'lucka' => 'lucie.fickerova@friendlyfyzio.cz',
        'renca' => 'renata.prnka@friendlyfyzio.cz',
        'sarka' => 'sarka.antosikova@friendlyfyzio.cz',
        'ema' => 'ema.murcova@friendlyfyzio.cz',
        'denisa' => 'denisa.novakova@friendlyfyzio.cz',
        'daniela' => 'daniela.steblova@friendlyfyzio.cz',
        'lada' => 'lada.cincilova@friendlyfyzio.cz',
        'kuba' => self::RENTAL,
        'jakub' => self::RENTAL,
    ];

    protected bool $dryRun = false;

    /** @var array<string, int> */
    protected array $stats = [];

    /** @var array<string, int> */
    protected array $unknownTitles = [];

    /** @var array<string, string> therapist e-mail => staff_profiles.id */
    protected array $profilesByEmail = [];

    /** @var array<string, Room> */
    protected array $rooms = [];

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        $path = $this->argument('path');

        if (! str_starts_with((string) $path, '/')) {
            $path = base_path($path);
        }

        if (! is_file($path)) {
            $this->error("Calendar snapshot not found: {$path}");

            return self::FAILURE;
        }

        $events = json_decode((string) file_get_contents($path), true);

        if (! is_array($events)) {
            $this->error('Snapshot is not a JSON array of events.');

            return self::FAILURE;
        }

        if ($this->dryRun) {
            $this->warn('Dry run — no changes will be written.');
        }

        $this->resolveTherapists();
        $today = Carbon::now(self::TIMEZONE)->startOfDay();

        foreach ($events as $event) {
            $this->importEvent($event, $today);
        }

        $this->printSummary();

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    protected function importEvent(array $event, Carbon $today): void
    {
        $summary = trim((string) ($event['summary'] ?? ''));
        $start = $this->parseInstant($event['start'] ?? null);
        $end = $this->parseInstant($event['end'] ?? null);

        if ($summary === '' || $start === null || $end === null || $end->lessThanOrEqualTo($start)) {
            $this->bump('invalid');

            return;
        }

        if ($start->copy()->startOfDay()->lessThan($today)) {
            $this->bump('past_skipped');

            return;
        }

        $parsed = $this->parseTitle($summary);

        if (isset($parsed['skip'])) {
            $this->bump('skipped_'.$parsed['skip']);

            return;
        }

        if (isset($parsed['rental'])) {
            $this->importRental($summary, $this->room($parsed['room']), $start, $end);

            return;
        }

        if (isset($parsed['unknown'])) {
            $this->unknownTitles[$parsed['unknown']] = ($this->unknownTitles[$parsed['unknown']] ?? 0) + 1;
            $this->bump('unknown_therapist');

            return;
        }

        $profileId = $this->profilesByEmail[$parsed['email']] ?? null;

        if ($profileId === null) {
            $this->unknownTitles[$parsed['email']] = ($this->unknownTitles[$parsed['email']] ?? 0) + 1;
            $this->bump('unknown_therapist');

            return;
        }

        $room = $this->room($parsed['room']);
        $workDate = $start->toDateString();
        $startTime = TherapistWorkBlock::normalizeTime($start->format('H:i'));
        $endTime = TherapistWorkBlock::normalizeTime($end->format('H:i'));

        $existing = TherapistWorkBlock::query()
            ->where('therapist_id', $profileId)
            ->where('work_date', $workDate)
            ->where('start_time', $startTime)
            ->first();

        if ($existing !== null) {
            $this->bump('existing');

            return;
        }

        $this->bump('created');

        if ($this->dryRun) {
            return;
        }

        TherapistWorkBlock::query()->create([
            'therapist_id' => $profileId,
            'room_id' => $room->getKey(),
            'work_date' => $workDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);
    }

    /**
     * Resolve one calendar title to a therapist + room, or to a rental of that
     * room. Order-agnostic: the room token (AV/VA/AM) can sit anywhere, and the
     * therapist is matched against the known-nickname dictionary. Device time is
     * skipped; an unrecognised name is surfaced for review, never guessed onto a
     * therapist.
     *
     * A rental still has to name a room — there is nothing to block otherwise —
     * so it is reported separately rather than folded into the plain `no_room`
     * count, where a tenant left unblocked would go unnoticed.
     *
     * @return array{email: string, room: string}|array{rental: true, room: string}|array{skip: string}|array{unknown: string}
     */
    protected function parseTitle(string $summary): array
    {
        $ascii = mb_strtolower(Str::ascii($this->normalizeWhitespace($summary)));

        // Rental wins over the device tag, as it always has: a rented room is
        // occupied whatever the tenant wheels into it.
        $isRental = str_contains($ascii, 'pronajem');

        if (! $isRental && (str_contains($ascii, 'laser') || str_contains($ascii, 'kryo'))) {
            return ['skip' => 'device'];
        }

        if (! preg_match('/\b(av|va|am|ma)\b/', $ascii, $match)) {
            return ['skip' => $isRental ? 'rental_no_room' : 'no_room'];
        }

        $room = in_array($match[1], ['av', 'va'], true) ? 'velka' : 'mala';

        if ($isRental) {
            return ['rental' => true, 'room' => $room];
        }

        foreach (self::NICKNAME_PATTERNS as $pattern => $resolution) {
            if (preg_match('/\b'.preg_quote($pattern, '/').'\b/u', $ascii) === 1) {
                return $resolution === self::RENTAL
                    ? ['rental' => true, 'room' => $room]
                    : ['email' => $resolution, 'room' => $room];
            }
        }

        return ['unknown' => $ascii];
    }

    /**
     * Record a rented stretch as a one-off room blocking, which is what keeps
     * the slot engine and the conflict finder from handing the room to a client.
     *
     * Idempotent on (room, start), the same shape the work blocks use.
     */
    protected function importRental(string $summary, Room $room, Carbon $start, Carbon $end): void
    {
        $startAt = $this->wallClock($start);
        $endAt = $this->wallClock($end);

        $existing = RoomBlocking::query()
            ->where('room_id', $room->getKey())
            ->where('is_recurring', false)
            ->where('start_at', $startAt)
            ->exists();

        if ($existing) {
            $this->bump('rentals_existing');

            return;
        }

        $this->bump('rentals_created');

        if ($this->dryRun) {
            return;
        }

        RoomBlocking::query()->create([
            'room_id' => $room->getKey(),
            'reason' => $this->rentalReason($summary),
            'is_recurring' => false,
            'start_at' => $startAt,
            'end_at' => $endAt,
        ]);
    }

    /**
     * The blocking's label in the calendar. Most titles already say "pronájem"
     * and name the tenant, so they are kept verbatim; the ones that only carry a
     * name ("Lucka A. - AM") are prefixed, or staff would read them as somebody's
     * shift.
     */
    protected function rentalReason(string $summary): string
    {
        $summary = $this->normalizeWhitespace($summary);

        return str_contains(mb_strtolower(Str::ascii($summary)), 'pronajem')
            ? $summary
            : 'Pronájem – '.$summary;
    }

    /**
     * Room blockings are stored as naive wall-clock datetimes — that is how the
     * calendar writes them and how {@see RoomBlockingIntervals} reads them back
     * (it takes the hour and minute verbatim). The app timezone is UTC, so the
     * Prague instant has to be re-read as local digits rather than converted, or
     * every imported rental would land two hours off.
     */
    protected function wallClock(Carbon $instant): Carbon
    {
        return Carbon::parse($instant->format('Y-m-d H:i:s'));
    }

    protected function normalizeWhitespace(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $value)) ?? '');
    }

    protected function parseInstant(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return rescue(fn (): Carbon => Carbon::parse($value)->setTimezone(self::TIMEZONE), null, false);
    }

    /**
     * Resolve the mapped therapist e-mails to profile ids. An e-mail whose
     * account isn't a bookable therapist is left out, so its events fall through
     * to the unknown handling instead of being imported as availability.
     */
    protected function resolveTherapists(): void
    {
        $emails = array_unique(array_filter(
            array_values(self::NICKNAME_PATTERNS),
            fn (string $value): bool => $value !== self::RENTAL,
        ));

        foreach ($emails as $email) {
            $profile = StaffProfile::query()
                ->whereHas('user', fn ($query) => $query->where('email', $email)->therapists())
                ->first();

            if ($profile !== null) {
                $this->profilesByEmail[$email] = $profile->getKey();
            } else {
                $this->warn("No therapist profile for {$email} — its blocks will be reported as unknown.");
            }
        }
    }

    protected function room(string $key): Room
    {
        $name = $key === 'velka' ? 'Ambulance velká' : 'Ambulance malá';

        return $this->rooms[$key] ??= Room::query()->where('name', $name)->firstOrFail();
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

        if ($this->unknownTitles !== []) {
            arsort($this->unknownTitles);
            $this->warn('Unrecognised titles (normalized → events, review these):');

            foreach ($this->unknownTitles as $title => $count) {
                $this->line("  {$title}: {$count}");
            }
        }
    }
}
