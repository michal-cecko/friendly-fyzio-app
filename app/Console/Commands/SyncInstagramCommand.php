<?php

namespace App\Console\Commands;

use App\Jobs\SyncInstagramConnectionJob;
use App\Models\InstagramConnection;
use Illuminate\Console\Command;

/**
 * Dispatches a sync job for every active, connected Instagram account. Scheduled
 * hourly; the sync itself refreshes tokens nearing their 60-day expiry.
 */
class SyncInstagramCommand extends Command
{
    protected $signature = 'instagram:sync';

    protected $description = 'Sync recent posts for all active Instagram connections';

    public function handle(): int
    {
        $connections = InstagramConnection::query()->activeConnected()->get();

        foreach ($connections as $connection) {
            $this->info("Dispatching sync for @{$connection->username}");
            SyncInstagramConnectionJob::dispatch($connection);
        }

        $this->info("Queued {$connections->count()} Instagram connection sync(s).");

        return self::SUCCESS;
    }
}
