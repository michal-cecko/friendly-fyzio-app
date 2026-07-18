<?php

namespace Tests\Feature\Credits;

use App\Enums\CreditTransactionType;
use App\Models\User;
use App\Support\Credits\CreditLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CreditLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_up_creates_account_and_raises_balance(): void
    {
        $client = User::factory()->customer()->create();

        $transaction = CreditLedger::record($client, 1000, CreditTransactionType::TopUp, 'Dárkový poukaz', now()->addMonths(6));

        $this->assertSame(1000, CreditLedger::balanceFor($client));
        $this->assertDatabaseHas('credit_accounts', ['client_id' => $client->id, 'balance' => 1000]);
        $this->assertDatabaseHas('credit_transactions', [
            'id' => $transaction->id,
            'amount' => 1000,
            'type' => CreditTransactionType::TopUp->value,
        ]);
    }

    public function test_deduction_lowers_balance_and_cannot_go_below_zero(): void
    {
        $client = User::factory()->customer()->create();
        CreditLedger::record($client, 500, CreditTransactionType::TopUp);

        CreditLedger::record($client, -300, CreditTransactionType::Deduction, 'Terapie');

        $this->assertSame(200, CreditLedger::balanceFor($client));

        $this->expectException(InvalidArgumentException::class);

        CreditLedger::record($client, -300, CreditTransactionType::Deduction);
    }

    public function test_amount_sign_must_match_type(): void
    {
        $client = User::factory()->customer()->create();

        $this->expectException(InvalidArgumentException::class);

        CreditLedger::record($client, -100, CreditTransactionType::TopUp);
    }

    public function test_nearest_expiry_skips_expired_top_ups(): void
    {
        $client = User::factory()->customer()->create();

        CreditLedger::record($client, 200, CreditTransactionType::TopUp, expiresAt: now()->addMonths(2));
        CreditLedger::record($client, 300, CreditTransactionType::TopUp, expiresAt: now()->addMonths(1));

        $this->assertTrue(CreditLedger::nearestExpiry($client)->isSameDay(now()->addMonths(1)));
    }
}
