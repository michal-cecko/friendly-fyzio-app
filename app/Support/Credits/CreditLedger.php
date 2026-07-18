<?php

namespace App\Support\Credits;

use App\Enums\CreditTransactionType;
use App\Models\CreditAccount;
use App\Models\CreditTransaction;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The single writer for client credit. Every balance change goes through
 * record(): a signed transaction row plus an atomic balance update on the
 * client's CreditAccount (created on first use). Top-ups are positive,
 * deductions and expirations negative; the balance can never drop below zero.
 */
class CreditLedger
{
    public static function record(
        User $client,
        int $amount,
        CreditTransactionType $type,
        ?string $description = null,
        ?CarbonInterface $expiresAt = null,
        ?CreditTransaction $related = null,
    ): CreditTransaction {
        if ($type === CreditTransactionType::TopUp && $amount <= 0) {
            throw new InvalidArgumentException('Dobití kreditu musí být kladné.');
        }

        if ($type !== CreditTransactionType::TopUp && $amount >= 0) {
            throw new InvalidArgumentException('Čerpání a propadnutí kreditu musí být záporné.');
        }

        return DB::transaction(function () use ($client, $amount, $type, $description, $expiresAt, $related): CreditTransaction {
            CreditAccount::query()->firstOrCreate(['client_id' => $client->getKey()]);

            /** @var CreditAccount $account */
            $account = CreditAccount::query()
                ->where('client_id', $client->getKey())
                ->lockForUpdate()
                ->first();

            if ($account->balance + $amount < 0) {
                throw new InvalidArgumentException('Zůstatek kreditu nemůže klesnout pod nulu.');
            }

            $transaction = CreditTransaction::create([
                'client_id' => $client->getKey(),
                'amount' => $amount,
                'type' => $type,
                'description' => $description,
                'expires_at' => $expiresAt,
                'related_transaction_id' => $related?->getKey(),
            ]);

            $account->increment('balance', $amount);

            return $transaction;
        });
    }

    public static function balanceFor(User $client): int
    {
        return (int) ($client->creditAccount()->value('balance') ?? 0);
    }

    /**
     * The soonest upcoming expiry among the client's top-ups that have not been
     * expired yet — shown on the credit balance banner.
     */
    public static function nearestExpiry(User $client): ?Carbon
    {
        return $client->creditTransactions()
            ->where('type', CreditTransactionType::TopUp)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '>=', today())
            ->whereDoesntHave('expirations')
            ->orderBy('expires_at')
            ->value('expires_at');
    }
}
