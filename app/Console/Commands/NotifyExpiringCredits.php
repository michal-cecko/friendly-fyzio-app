<?php

namespace App\Console\Commands;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Notifications\CreditsExpiringNotification;
use App\Support\Credits\CreditLedger;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Warns clients that their credit is about to expire: every not-yet-expired
 * top-up whose validity date falls within the configured notice window gets one
 * reminder e-mail (per client, covering the soonest expiry). The
 * expiry_notified_at marker keeps the daily run from sending twice. A notice
 * window of 0 disables the notification.
 */
class NotifyExpiringCredits extends Command
{
    protected $signature = 'credits:notify-expiring';

    protected $description = 'Upozorní klienty na blížící se vypršení kreditu';

    public function handle(): int
    {
        $days = Settings::creditExpiryNoticeDays();

        if ($days <= 0) {
            $this->info('Upozornění na vypršení kreditu je vypnuté.');

            return self::SUCCESS;
        }

        $notified = 0;

        CreditTransaction::query()
            ->where('type', CreditTransactionType::TopUp)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '>=', today())
            ->whereDate('expires_at', '<=', today()->addDays($days))
            ->whereDoesntHave('expirations')
            ->whereNull('expiry_notified_at')
            ->orderBy('expires_at')
            ->with('client')
            ->get()
            ->groupBy('client_id')
            ->each(function (Collection $topUps) use (&$notified): void {
                /** @var CreditTransaction $soonest */
                $soonest = $topUps->first();
                $client = $soonest->client;

                if ($client === null || blank($client->email)) {
                    return;
                }

                $balance = CreditLedger::balanceFor($client);

                if ($balance <= 0) {
                    // Nothing left on the account — no credit is actually at risk.
                    return;
                }

                $client->notify(new CreditsExpiringNotification($soonest, $balance));

                $topUps->each(function (CreditTransaction $topUp): void {
                    $topUp->forceFill(['expiry_notified_at' => now()])->save();
                });

                $notified++;
            });

        $this->info("Upozornění na vypršení kreditu odesláno: {$notified}");

        return self::SUCCESS;
    }
}
