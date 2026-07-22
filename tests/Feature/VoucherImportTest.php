<?php

namespace Tests\Feature;

use App\Console\Commands\VoucherImport;
use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\User;
use App\Support\Credits\CreditLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class VoucherImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The fixture's validity months are read against "now", so the clock is
        // pinned to keep 1/26 in the past and 11/26–1/27 in the future.
        $this->travelTo('2026-07-21 10:00:00');
    }

    protected function runImport(bool $dryRun = false): void
    {
        $this->artisan('vouchers:import', array_filter([
            'path' => 'tests/Fixtures/googlesheets',
            '--dry-run' => $dryRun,
        ]))->assertSuccessful();
    }

    protected function creditFor(string $name): int
    {
        // Matched in PHP: SQLite's lower() is ASCII-only, so a name with Czech
        // diacritics would never be found.
        $client = User::query()->get()->firstOrFail(
            fn (User $user): bool => mb_strtolower($user->name) === mb_strtolower($name),
        );

        return CreditLedger::balanceFor($client);
    }

    public function test_credits_an_existing_client_without_creating_a_duplicate(): void
    {
        $existing = User::factory()->customer()->create(['name' => 'Klára Existující']);

        $this->runImport();

        $this->assertSame(1, User::query()->where('name', 'Klára Existující')->count());
        $this->assertSame(2000, CreditLedger::balanceFor($existing->fresh()));

        $transaction = CreditTransaction::query()
            ->where('client_id', $existing->getKey())
            ->firstOrFail();

        $this->assertSame(CreditTransactionType::TopUp, $transaction->type);
        $this->assertStringContainsString('ABC123', $transaction->description);
        $this->assertSame('2026-12-31', $transaction->expires_at->toDateString(), 'Valid to the end of the month.');
    }

    public function test_creates_a_placeholder_client_for_an_unknown_holder(): void
    {
        $this->runImport();

        $created = User::query()->where('name', 'Nová Držitelka')->firstOrFail();

        $this->assertSame('poukaz+nova-drzitelka@friendlyfyzio.cz', $created->email);
        $this->assertNull($created->email_verified_at, 'Placeholder holders are never e-mailed.');
        $this->assertTrue($created->tags->pluck('name')->contains(VoucherImport::IMPORT_TAG));
        $this->assertSame(1300, CreditLedger::balanceFor($created), 'Parses "1 300 Kc" with the odd spelling.');
    }

    public function test_a_partly_spent_voucher_credits_only_the_remainder(): void
    {
        $this->runImport();

        // The sheet says 2 000 Kč face value but "zb.750 kč" was left after a
        // redemption — crediting the face value would honour money already taken.
        $this->assertSame(750, $this->creditFor('Petra Částečná'));
    }

    public function test_skips_redeemed_expired_and_holderless_vouchers(): void
    {
        $this->runImport();

        foreach (['Jana Vyčerpaná', 'Marie Propadlá'] as $name) {
            $this->assertNull(
                User::query()->where('name', $name)->first(),
                "{$name} owes nothing, so no client or credit should be created.",
            );
        }

        // Bearer vouchers ("?", tombola) name nobody and cannot become credit.
        $this->assertSame(0, User::query()->where('name', 'like', '%tombol%')->count());
        // Five vouchers carry a balance: Klára, Nová, Petra, Bára and Eva.
        $this->assertSame(5, CreditTransaction::query()->count());
    }

    public function test_prices_a_named_service_voucher_from_the_documented_list(): void
    {
        $this->runImport();

        // "baby masáž / výhra Den dětí" carries no price; Masáže miminek a dětí.
        $this->assertSame(500, $this->creditFor('Bára Bezceny'));
    }

    public function test_imported_credit_never_triggers_the_expiry_reminder(): void
    {
        Notification::fake();

        $this->runImport();

        $this->assertSame(
            0,
            CreditTransaction::query()->whereNull('expiry_notified_at')->count(),
            'Every imported top-up is marked as already notified.',
        );

        $this->artisan('credits:notify-expiring')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_is_idempotent_including_the_voucher_without_a_code(): void
    {
        $this->runImport();

        $transactions = CreditTransaction::query()->count();
        $clients = User::query()->count();
        $balance = $this->creditFor('Eva Bezkódu');

        $this->runImport();

        $this->assertSame($transactions, CreditTransaction::query()->count());
        $this->assertSame($clients, User::query()->count());
        $this->assertSame($balance, $this->creditFor('Eva Bezkódu'), 'A code-less voucher is not credited twice.');
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->runImport(dryRun: true);

        $this->assertSame(0, CreditTransaction::query()->count());
        $this->assertSame(0, User::query()->count());
    }
}
