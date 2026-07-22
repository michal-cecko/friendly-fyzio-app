<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Building;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Imports the Laser+Kryo Google Calendar as **laser reservations** (one per
 * event) under the existing "Laserová terapie" service, in a dedicated "Laser"
 * room. Read through the Google Calendar API and dumped to a JSON snapshot this
 * command parses; each entry is `{ "summary": "...", "start": ISO, "end": ISO }`.
 *
 * ---------------------------------------------------------------------------
 * Decisions encoded below (owner-confirmed) — do not "simplify".
 * ---------------------------------------------------------------------------
 *
 * The calendar holds two kinds of 10-minute slot, both run by one device
 * operator:
 *  - `{Client} - laser a kryo`  → a real appointment. Client matched by name
 *    (no e-mails in titles), a placeholder customer created when unknown, like
 *    the ergobody/voucher imports.
 *  - `Denisa - laser/kryo`      → an empty operator slot (no client). Filed
 *    against a single shared "bez klienta" placeholder so the schedule is
 *    complete without inventing a patient.
 *
 * Therapist is the operator: **Denisa Nováková** by default, overridden when the
 * title tags someone else ("… - EMA"). Only ONE reservation is created per event
 * (laser only, owner decision): a therapist can't hold two reservations at the
 * same instant (the no-double-booking index is therapist+date+time, room-blind),
 * so a paired kryo reservation at the same time is impossible — kryo is left out.
 *
 * No client e-mail can result: rows are `Confirmed` with `reminder_sent_at`
 * pre-set, so {@see SendReservationReminders} (Confirmed +
 * null reminder) and {@see SendReservationConfirmations}
 * (Pending only) both pass them by; and each row is a raw insert, so the
 * reservation-created notification observer never fires. Past events and
 * activity logging are skipped. Idempotent on (therapist, date, start time) —
 * the same key the unique index enforces.
 */
class LaserImport extends Command
{
    protected $signature = 'laser:import
        {path=export/googlecalendar/laser-kryo.json : JSON snapshot of the Laser+Kryo calendar}
        {--dry-run : Parse and report without writing anything}';

    protected $description = 'Importuje laserové rezervace z Google kalendáře Laser+Kryo.';

    public const string IMPORT_TAG = 'Laser import';

    protected const string TIMEZONE = 'Europe/Prague';

    protected const string SERVICE_SLUG = 'laserova-terapie';

    protected const string ROOM_NAME = 'Laser';

    protected const string DEFAULT_OPERATOR = 'denisa.novakova@friendlyfyzio.cz';

    protected const string NO_CLIENT_EMAIL = 'laser-bez-klienta@friendlyfyzio.cz';

    /**
     * Names (normalized) that identify a device operator rather than a client,
     * mapping to their login e-mail. A bare operator name = an empty slot; the
     * same names also resolve a trailing therapist tag ("… - EMA").
     *
     * @var array<string, string>
     */
    protected const array THERAPISTS = [
        'denisa' => 'denisa.novakova@friendlyfyzio.cz',
        'ema' => 'ema.murcova@friendlyfyzio.cz',
        'renca' => 'renata.prnka@friendlyfyzio.cz',
        'sarka' => 'sarka.antosikova@friendlyfyzio.cz',
        'lucka f' => 'lucie.fickerova@friendlyfyzio.cz',
        'daniela' => 'daniela.steblova@friendlyfyzio.cz',
        'lada' => 'lada.cincilova@friendlyfyzio.cz',
    ];

    protected bool $dryRun = false;

    /** @var array<string, int> */
    protected array $stats = [];

    /** @var list<string> */
    protected array $warnings = [];

    protected ?Service $service = null;

    protected ?Room $room = null;

    /** @var array<string, string> therapist e-mail => staff_profiles.id */
    protected array $profilesByEmail = [];

    /** @var array<string, string>|null lowercased name => user id */
    protected ?array $clientsByName = null;

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

        $this->service = Service::query()->where('slug', self::SERVICE_SLUG)->first();

        if ($this->service === null) {
            $this->error('Service "Laserová terapie" not found — run ServiceSeeder first.');

            return self::FAILURE;
        }

        if ($this->dryRun) {
            $this->warn('Dry run — no changes will be written.');
        }

        // Bulk import must not flood the audit trail.
        activity()->disableLogging();

        $this->resolveTherapists();
        $today = Carbon::now(self::TIMEZONE)->startOfDay();

        foreach ($events as $event) {
            $this->importEvent($event, $today);
        }

        activity()->enableLogging();

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

        [$leading, $tag] = $this->splitTitle($summary);
        $leadingNick = $this->normalizeName($leading);

        // Bare operator name (e.g. "Denisa") = an empty slot; anything else is a client.
        $operatorEmail = self::THERAPISTS[$leadingNick] ?? null;
        $isEmptySlot = $operatorEmail !== null;

        $therapistEmail = $isEmptySlot
            ? $operatorEmail
            : ($this->tagToTherapist($tag) ?? self::DEFAULT_OPERATOR);

        $profileId = $this->profilesByEmail[$therapistEmail] ?? null;

        if ($profileId === null) {
            $this->warnings[] = "No therapist profile for {$therapistEmail} (title “{$summary}”).";
            $this->bump('skipped_no_therapist');

            return;
        }

        $workDate = $start->toDateString();
        $startTime = $start->format('H:i:s');

        if ($this->reservationExists($profileId, $workDate, $startTime)) {
            $this->bump('existing');

            return;
        }

        $this->bump($isEmptySlot ? 'created_empty' : 'created_client');

        if ($this->dryRun) {
            return;
        }

        $client = $isEmptySlot ? $this->noClient() : $this->resolveClient($leading);

        $this->insertReservation($client, $profileId, $workDate, $startTime, $end->format('H:i:s'));
    }

    /**
     * Split a title into its leading name and any trailing therapist tag around
     * the "laser[/ a ]kryo" service phrase. "Anet Župníková - laser/kryo - EMA"
     * → ["Anet Župníková", "ema"]; "Denisa - laser/kryo" → ["Denisa", ""].
     *
     * @return array{0: string, 1: string}
     */
    protected function splitTitle(string $summary): array
    {
        $summary = $this->normalizeWhitespace($summary);
        $leading = trim((string) preg_replace('/[\s\-–—]*laser.*/iu', '', $summary), ' -–—');

        $tag = '';

        if (preg_match('/laser\s*[\/a ]*(?:kryo)?\b(.*)$/iu', $summary, $match) === 1) {
            $tag = $this->normalizeName(trim($match[1], ' -–—.'));
        }

        return [$leading, $tag];
    }

    protected function tagToTherapist(string $tag): ?string
    {
        return $tag === '' ? null : (self::THERAPISTS[$tag] ?? null);
    }

    protected function reservationExists(string $profileId, string $date, string $startTime): bool
    {
        return Reservation::query()
            ->where('therapist_id', $profileId)
            ->where('reservation_date', $date)
            ->where('start_time', $startTime)
            ->exists();
    }

    protected function insertReservation(User $client, string $profileId, string $date, string $startTime, string $endTime): void
    {
        $now = Carbon::now();

        // Raw insert on purpose: the reservation-created observer would send the
        // legacy "new booking" notification and scan @-mentions.
        Reservation::query()->insert([
            'id' => (new Reservation)->newUniqueId(),
            'client_id' => $client->getKey(),
            'service_id' => $this->service->getKey(),
            'therapist_id' => $profileId,
            'room_id' => $this->room()->getKey(),
            'reservation_date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => ReservationStatus::Confirmed->value,
            'payment_status' => PaymentStatus::Unpaid->value,
            // Pre-armed so the reminder sweep skips these imported bookings.
            'reminder_sent_at' => $now,
            'imported_at' => $now,
            'is_control_therapy' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * The client behind a titled appointment: matched by name, else a
     * placeholder customer (the sheet carries no e-mail), tagged for provenance
     * and shielded from prune-unverified.
     */
    protected function resolveClient(string $name): User
    {
        $this->clientsByName ??= User::query()->select(['id', 'name'])->get()
            ->mapWithKeys(fn (User $u): array => [mb_strtolower(trim((string) $u->name)) => $u->getKey()])->all();

        $key = mb_strtolower($this->normalizeWhitespace($name));

        if ($id = $this->clientsByName[$key] ?? null) {
            return User::query()->findOrFail($id);
        }

        $client = new User;
        $client->fill(['name' => $this->normalizeWhitespace($name), 'email' => $this->placeholderEmail($name)]);
        $client->forceFill(['password' => Str::password(40)]);
        $client->save();
        $client->markAsCustomer();
        $client->attachTag(self::IMPORT_TAG);

        $this->clientsByName[$key] = $client->getKey();
        $this->bump('clients_created');

        return $client;
    }

    protected function placeholderEmail(string $name): string
    {
        $base = 'laser+'.(Str::slug($name) ?: 'klient');
        $email = $base.'@friendlyfyzio.cz';

        for ($suffix = 2; User::query()->where('email', $email)->exists(); $suffix++) {
            $email = $base.'-'.$suffix.'@friendlyfyzio.cz';
        }

        return $email;
    }

    /**
     * The single shared "no client" placeholder that empty operator slots are
     * filed against. Not a customer (kept out of Klienti).
     */
    protected function noClient(): User
    {
        $user = User::query()->firstOrNew(['email' => self::NO_CLIENT_EMAIL]);

        if (! $user->exists) {
            $user->fill(['name' => 'Laser – bez klienta']);
            $user->forceFill(['password' => Str::password(40)]);
            $user->save();
            $user->attachTag(self::IMPORT_TAG);
        }

        return $user;
    }

    protected function resolveTherapists(): void
    {
        foreach (array_unique(array_values(self::THERAPISTS)) as $email) {
            $profile = StaffProfile::query()
                ->whereHas('user', fn ($query) => $query->where('email', $email))
                ->first();

            if ($profile !== null) {
                $this->profilesByEmail[$email] = $profile->getKey();
            }
        }
    }

    protected function room(): Room
    {
        return $this->room ??= Room::query()->firstOrCreate(
            ['name' => self::ROOM_NAME],
            [
                'building_id' => Building::query()->firstOrCreate(
                    ['name' => 'Hlavní budova'],
                    ['address' => 'Zednická 1109/2, Ostrava-Poruba'],
                )->getKey(),
                'short_name' => 'L',
            ],
        );
    }

    protected function parseInstant(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return rescue(fn (): Carbon => Carbon::parse($value)->setTimezone(self::TIMEZONE), null, false);
    }

    protected function normalizeWhitespace(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $value)) ?? '');
    }

    protected function normalizeName(string $value): string
    {
        return trim(mb_strtolower(Str::ascii($this->normalizeWhitespace(str_replace('.', '', $value)))));
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

        if ($this->warnings !== []) {
            $this->warn('Notes:');

            foreach (array_slice($this->warnings, 0, 30) as $message) {
                $this->line('  '.$message);
            }
        }
    }
}
