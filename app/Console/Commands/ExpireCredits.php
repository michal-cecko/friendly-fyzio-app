<?php

namespace App\Console\Commands;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Support\Credits\CreditLedger;
use Illuminate\Console\Command;

/**
 * Expires client credit: every top-up whose validity date has passed and that
 * has not been expired yet gets a matching Expiration row (linked through
 * related_transaction_id, which makes re-runs idempotent). At most the
 * client's remaining balance is written off, so partially spent top-ups
 * expire only what is left.
 */
class ExpireCredits extends Command
{
    protected $signature = 'credits:expire';

    protected $description = 'Propadne kredit z dobití po datu platnosti';

    public function handle(): int
    {
        $expired = 0;

        CreditTransaction::query()
            ->where('type', CreditTransactionType::TopUp)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', today())
            ->whereDoesntHave('expirations')
            ->orderBy('expires_at')
            ->with('client')
            ->get()
            ->each(function (CreditTransaction $topUp) use (&$expired): void {
                $client = $topUp->client;

                if ($client === null) {
                    return;
                }

                $amount = min(CreditLedger::balanceFor($client), $topUp->amount);

                if ($amount <= 0) {
                    // Balance already fully spent — nothing left to write off.
                    return;
                }

                CreditLedger::record(
                    $client,
                    -$amount,
                    CreditTransactionType::Expiration,
                    'Propadnutí kreditu z dobití '.$topUp->created_at->format('j. n. Y'),
                    related: $topUp,
                );

                $expired++;
            });

        $this->info("Propadlé kredity: {$expired}");

        return self::SUCCESS;
    }
}
