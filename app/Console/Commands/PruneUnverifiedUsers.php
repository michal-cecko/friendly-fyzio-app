<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Removes abandoned/bot self-registrations: customer accounts that never verified
 * their email within the grace window. Staff accounts are never touched.
 */
class PruneUnverifiedUsers extends Command
{
    protected $signature = 'users:prune-unverified {--hours=72 : Delete unverified customers older than this many hours}';

    protected $description = 'Delete unverified customer accounts older than the grace window';

    public function handle(): int
    {
        $cutoff = now()->subHours((int) $this->option('hours'));

        $deleted = User::query()
            ->where('role', UserRole::Customer)
            ->whereNull('email_verified_at')
            ->where('created_at', '<', $cutoff)
            ->get()
            ->each(fn (User $user) => $user->forceDelete())
            ->count();

        $this->info("Pruned {$deleted} unverified customer account(s).");

        return self::SUCCESS;
    }
}
