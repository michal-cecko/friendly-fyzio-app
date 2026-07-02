<?php

namespace App\Jobs;

use App\Models\InstagramConnection;
use App\Support\Instagram\InstagramException;
use App\Support\Instagram\SyncInstagramPosts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Syncs a single Instagram connection's recent posts on the queue. A failed sync
 * already records its error on the connection, so the job swallows the exception
 * to avoid retry storms against Meta's rate limits.
 */
class SyncInstagramConnectionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public InstagramConnection $instagramConnection,
    ) {}

    public function handle(SyncInstagramPosts $sync): void
    {
        try {
            $sync($this->instagramConnection);
        } catch (InstagramException) {
            // The connection's status/last_error was already persisted by the sync.
        }
    }
}
