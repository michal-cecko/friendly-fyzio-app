<?php

namespace App\Console\Commands;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\User;
use App\Support\Credits\CreditLedger;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Imports the outstanding gift vouchers from the clinic's Google Sheet as
 * client credit.
 *
 * There is deliberately no voucher subsystem: B2 was closed by decision
 * (docs/remaining-work.md) — vouchers are sold through a SimpleShop iframe on a
 * CMS page, and a therapist credits the client's account when one is presented
 * (docs/master-specification.md:944). So each live voucher becomes a single
 * TopUp on the credit ledger, with its code kept in the description so staff can
 * find it. The orphaned GiftVoucher model stays unused, as the docs intend.
 *
 * What is imported, and what is not:
 *  - **Live vouchers** — credited at their *remaining* value. Three are only
 *    partly spent and record the remainder in free text ("zůstatek 1 100 Kč",
 *    "zbývá 400kc"); that remainder wins over the printed face value, otherwise
 *    the clinic would honour money already taken.
 *  - **Fully redeemed** ones are skipped: nothing is owed.
 *  - **Expired** ones are skipped and listed, for the owner to judge case by
 *    case (owner decision, 2026-07-21).
 *  - **Bearer vouchers** (tombola prizes, "Kulíšek") carry no holder at all, so
 *    they cannot become anyone's credit; they are reported and left to the desk.
 *
 * Holders are matched by name — the sheet has no e-mail column — which is safe
 * here only because no two live holders collide with each other or with an
 * existing client. Anyone unknown gets a placeholder client account, exactly
 * like the ergobody import, so the credit is waiting when they walk in.
 *
 * Imported credit is stamped as already notified, so the expiry reminder can
 * never fire for it; MAIL_SUPPRESS_NON_ADMIN is the wider guard.
 */
class VoucherImport extends Command
{
    protected $signature = 'vouchers:import
        {path=export/googlesheets : Directory containing the voucher CSV export}
        {--dry-run : Parse and report without writing anything}';

    protected $description = 'Importuje zůstatky dárkových poukazů jako kredit klientů.';

    public const string IMPORT_TAG = 'Dárkový poukaz';

    /**
     * Vouchers whose "Hodnota" names a service rather than a price. Only ones
     * with an identifiable holder can be credited; the value follows the
     * documented price list (docs/master-specification.md:1397).
     *
     * @var array<string, int>
     */
    protected const array SERVICE_VALUES = [
        'baby masáž' => 500,
    ];

    protected bool $dryRun = false;

    /** @var array<string, int> */
    protected array $stats = [];

    /** @var list<string> */
    protected array $skipped = [];

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        $file = $this->findCsv();

        if ($file === null) {
            $this->error('Voucher export not found (expected a CSV containing "poukaz" in '.$this->argument('path').').');

            return self::FAILURE;
        }

        if ($this->dryRun) {
            $this->warn('Dry run — no changes will be written.');
        }

        activity()->disableLogging();

        $credited = 0;

        foreach ($this->readRows($file) as $row) {
            $credited += $this->importVoucher($row);
        }

        activity()->enableLogging();

        $this->printSummary($credited);

        return self::SUCCESS;
    }

    protected function findCsv(): ?string
    {
        $directory = $this->argument('path');

        if (! str_starts_with($directory, '/')) {
            $directory = base_path($directory);
        }

        foreach (glob($directory.'/*.csv') ?: [] as $candidate) {
            if (Str::contains(Str::ascii(basename($candidate)), 'poukaz', ignoreCase: true)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array{name: string, kind: string, value: string, code: string, valid: string, usedOn: string, usedBy: string}  $row
     * @return int 1 when credit was recorded
     */
    protected function importVoucher(array $row): int
    {
        $name = $this->cleanName($row['name']);
        $code = mb_strtoupper(trim($row['code']));
        $label = $name !== '' ? $name : '(bez jména)';

        [$remaining, $state] = $this->remainingValue($row);

        if ($state === 'redeemed') {
            $this->bump('fully_redeemed');

            return 0;
        }

        if ($name === '') {
            // A bearer voucher — a tombola prize or a partner's "Kulíšek" card.
            // Nobody's account can hold it, so the desk honours it on sight.
            $this->bump('bearer_no_holder');
            $this->skipped[] = sprintf('bez držitele: %s (%s)', $row['kind'] ?: '—', $row['valid']);

            return 0;
        }

        $expiresAt = $this->expiryFrom($row['valid']);

        if ($expiresAt === null) {
            $this->bump('invalid_expiry');
            $this->skipped[] = "{$label}: nečitelná platnost '{$row['valid']}'";

            return 0;
        }

        if ($expiresAt->isPast()) {
            $this->bump('expired');
            $this->skipped[] = sprintf('%s: propadlý %s, %s Kč', $label, $expiresAt->format('n/y'), $remaining ?? '?');

            return 0;
        }

        if ($remaining === null) {
            $this->bump('no_value');
            $this->skipped[] = "{$label}: bez uvedené hodnoty ({$row['kind']})";

            return 0;
        }

        $description = $this->describe($code, $row['kind']);
        $client = $this->findClient($name);

        if ($client && $this->alreadyImported($client, $code, $remaining, $expiresAt)) {
            $this->bump('already_imported');

            return 0;
        }

        $this->bump($state === 'partial' ? 'credited_partial' : 'credited_full');
        $this->stats['credited_value'] = ($this->stats['credited_value'] ?? 0) + $remaining;

        if ($this->dryRun) {
            return 1;
        }

        $client ??= $this->createClient($name);

        $transaction = CreditLedger::record(
            $client,
            $remaining,
            CreditTransactionType::TopUp,
            $description,
            $expiresAt,
        );

        // Imported credit must never trigger the "your credit expires soon"
        // notice; the marker is the same one the daily job sets after sending.
        $transaction->forceFill(['expiry_notified_at' => now()])->save();

        return 1;
    }

    /**
     * The amount still owed on a voucher, and how it was determined. A written
     * remainder always beats the face value; a redemption date with no
     * remainder means it was spent in full.
     *
     * @param  array<string, string>  $row
     * @return array{0: ?int, 1: string}
     */
    protected function remainingValue(array $row): array
    {
        if (preg_match('/(?:zůstatek|zbývá|zb\.?)\s*([\d\s]+)/iu', $row['usedBy'], $matches)) {
            return [(int) preg_replace('/\D/', '', $matches[1]), 'partial'];
        }

        if (trim($row['usedOn']) !== '') {
            return [null, 'redeemed'];
        }

        return [$this->parseMoney($row['value']) ?? $this->serviceValue($row['kind']), 'unused'];
    }

    /**
     * Values are written six different ways across the sheet — "2 000 Kč",
     * "2 000 Kc", "2000", "1 300Kč", "1400 Kc", "900" — so only the digits
     * matter. Non-numeric entries ("výhra Den dětí") yield null.
     */
    protected function parseMoney(string $value): ?int
    {
        $digits = preg_replace('/\D/u', '', $value) ?? '';

        return $digits === '' ? null : (int) $digits;
    }

    protected function serviceValue(string $kind): ?int
    {
        $kind = mb_strtolower(trim($kind));

        foreach (self::SERVICE_VALUES as $needle => $price) {
            if (str_contains($kind, $needle)) {
                return $price;
            }
        }

        return null;
    }

    /**
     * "Platnost" is a month, e.g. 8/26 — the voucher is good until its end.
     */
    protected function expiryFrom(string $validity): ?Carbon
    {
        if (! preg_match('#^(\d{1,2})\s*/\s*(\d{2})$#', trim($validity), $matches)) {
            return null;
        }

        return rescue(
            fn (): Carbon => Carbon::create(2000 + (int) $matches[2], (int) $matches[1], 1)->endOfMonth(),
            null,
            false,
        );
    }

    protected function describe(string $code, string $kind): string
    {
        $kind = trim($kind);

        return 'Dárkový poukaz'
            .($code !== '' ? ' '.$code : '')
            .($kind !== '' ? ' – '.$kind : '');
    }

    /**
     * Re-runs must not stack credit. A coded voucher is identified by its code
     * in the description; the code-less ones by holder, amount and expiry.
     */
    protected function alreadyImported(User $client, string $code, ?int $remaining, Carbon $expiresAt): bool
    {
        $query = CreditTransaction::query()
            ->where('type', CreditTransactionType::TopUp)
            ->where('client_id', $client->getKey());

        if ($code !== '') {
            return $query->where('description', 'like', '%'.$code.'%')->exists();
        }

        return $query
            ->where('amount', $remaining)
            ->whereDate('expires_at', $expiresAt->toDateString())
            ->exists();
    }

    /**
     * Names are compared in PHP rather than with SQL `lower()`, which is
     * ASCII-only on SQLite and would leave "Petra Částečná" unmatched — silently
     * creating a second client and double-crediting on the next run.
     *
     * @var array<string, string>|null
     */
    protected ?array $clientsByName = null;

    protected function findClient(string $name): ?User
    {
        $this->clientsByName ??= User::query()
            ->select(['id', 'name'])
            ->get()
            ->mapWithKeys(fn (User $user): array => [mb_strtolower(trim($user->name)) => $user->getKey()])
            ->all();

        $id = $this->clientsByName[mb_strtolower($name)] ?? null;

        return $id === null ? null : User::query()->find($id);
    }

    /**
     * A placeholder client for a holder we have never treated — the sheet
     * carries no e-mail, so contact details are filled in at the desk. The tag
     * marks provenance and shields the account from prune-unverified.
     */
    protected function createClient(string $name): User
    {
        $client = new User;
        $client->fill([
            'name' => $name,
            'email' => $this->placeholderEmail($name),
        ]);
        $client->forceFill(['password' => Str::password(40)]);
        $client->save();
        $client->markAsCustomer();
        $client->attachTag(self::IMPORT_TAG);

        // Keep the lookup fresh so a second voucher for the same new holder
        // lands on this account instead of creating another.
        $this->clientsByName[mb_strtolower(trim($name))] = $client->getKey();

        $this->bump('clients_created');

        return $client;
    }

    /**
     * A voucher has no holder only when the sheet names nobody: a bare "?" or a
     * tombola prize. A half-known name such as "Martina ?" is kept verbatim —
     * the person is real and their credit is owed, and leaving the question mark
     * in place shows staff the surname is still missing instead of quietly
     * inventing one.
     */
    /**
     * Placeholder address for a holder we have no contact details for. Two
     * half-known names could slug identically ("Martina ?"), and users.email is
     * unique, so a suffix is added rather than letting the import fail.
     */
    protected function placeholderEmail(string $name): string
    {
        $base = 'poukaz+'.(Str::slug($name) ?: 'neznamy');
        $email = $base.'@friendlyfyzio.cz';

        for ($suffix = 2; User::query()->where('email', $email)->exists(); $suffix++) {
            $email = $base.'-'.$suffix.'@friendlyfyzio.cz';
        }

        return $email;
    }

    protected function cleanName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

        if ($name === '?' || Str::contains($name, 'tombol', ignoreCase: true)) {
            return '';
        }

        return $name;
    }

    /**
     * @return iterable<int, array{name: string, kind: string, value: string, code: string, valid: string, usedOn: string, usedBy: string}>
     */
    protected function readRows(string $file): iterable
    {
        $handle = fopen($file, 'r');

        if ($handle === false) {
            return;
        }

        while (($row = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            $row = array_map(fn ($cell): string => (string) $cell, array_pad($row, 8, ''));

            // The sheet is laid out with a leading spacer column, a banner row
            // and a header row; only rows carrying a Platnost are vouchers.
            if (trim($row[5]) === '' || trim($row[5]) === 'Platnost') {
                continue;
            }

            yield [
                'name' => $row[1],
                'kind' => $row[2],
                'value' => $row[3],
                'code' => $row[4],
                'valid' => $row[5],
                'usedOn' => $row[6],
                'usedBy' => $row[7],
            ];
        }

        fclose($handle);
    }

    protected function bump(string $key): void
    {
        $this->stats[$key] = ($this->stats[$key] ?? 0) + 1;
    }

    protected function printSummary(int $credited): void
    {
        $this->newLine();
        $this->info($this->dryRun ? 'Dry run summary:' : 'Import summary:');

        ksort($this->stats);

        $this->table(
            ['Metrika', 'Počet'],
            collect($this->stats)->map(fn (int $count, string $key): array => [$key, $count])->values()->all(),
        );

        $this->line("Poukazů převedeno na kredit: {$credited}");

        if ($this->skipped !== []) {
            $this->newLine();
            $this->warn('Nepřevedené poukazy (k ručnímu posouzení):');

            foreach ($this->skipped as $line) {
                $this->line('  '.$line);
            }
        }
    }
}
