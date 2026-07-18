<?php

namespace Tests\Feature\Credits;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\User;
use App\Support\Credits\CreditLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireCreditsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_top_up_is_written_off_once(): void
    {
        $client = User::factory()->customer()->create();

        $topUp = CreditLedger::record($client, 500, CreditTransactionType::TopUp, expiresAt: now()->subDay());
        CreditLedger::record($client, 300, CreditTransactionType::TopUp, expiresAt: now()->addMonths(3));

        $this->artisan('credits:expire')->assertSuccessful();

        $this->assertSame(300, CreditLedger::balanceFor($client));
        $this->assertDatabaseHas('credit_transactions', [
            'type' => CreditTransactionType::Expiration->value,
            'amount' => -500,
            'related_transaction_id' => $topUp->id,
        ]);

        // Idempotent: a second run must not expire the same top-up again.
        $this->artisan('credits:expire')->assertSuccessful();

        $this->assertSame(300, CreditLedger::balanceFor($client));
        $this->assertSame(1, CreditTransaction::query()->where('type', CreditTransactionType::Expiration)->count());
    }

    public function test_partially_spent_top_up_expires_only_the_remainder(): void
    {
        $client = User::factory()->customer()->create();

        CreditLedger::record($client, 500, CreditTransactionType::TopUp, expiresAt: now()->subDay());
        CreditLedger::record($client, -400, CreditTransactionType::Deduction, 'Terapie');

        $this->artisan('credits:expire')->assertSuccessful();

        $this->assertSame(0, CreditLedger::balanceFor($client));
        $this->assertDatabaseHas('credit_transactions', [
            'type' => CreditTransactionType::Expiration->value,
            'amount' => -100,
        ]);
    }

    public function test_unexpired_top_ups_are_untouched(): void
    {
        $client = User::factory()->customer()->create();

        CreditLedger::record($client, 500, CreditTransactionType::TopUp, expiresAt: now()->addDay());

        $this->artisan('credits:expire')->assertSuccessful();

        $this->assertSame(500, CreditLedger::balanceFor($client));
        $this->assertSame(0, CreditTransaction::query()->where('type', CreditTransactionType::Expiration)->count());
    }
}
