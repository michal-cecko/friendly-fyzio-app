<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Removes abandoned/bot self-registrations: customer accounts that never verified
 * their email within the grace window AND have no activity of any kind. Staff
 * accounts are never touched.
 *
 * Reservation customers are created unverified and passwordless on purpose (they
 * never need to log in — see CreateReservationFromWizard), so "unverified" alone
 * is not abandonment. A customer with any domain record (a reservation, invoice,
 * enrollment, therapy record, credit movement, waitlist entry, note, or token) is
 * a real profile and must be preserved — deleting them would cascade-delete those
 * records too.
 */
class PruneUnverifiedUsers extends Command
{
    protected $signature = 'users:prune-unverified {--hours=72 : Delete unverified customers older than this many hours}';

    protected $description = 'Delete unverified, activity-free customer accounts older than the grace window';

    /**
     * Relationships whose presence marks a real customer we must never delete.
     * (clientProfile is intentionally excluded — it is auto-created for every
     * customer and so is not a signal of real activity.)
     *
     * @var list<string>
     */
    protected array $activityRelations = [
        'reservations',
        'invoices',
        'courseEnrollments',
        'creditTransactions',
        'waitlistEntries',
        'clientNotes',
        'substituteTokens',
    ];

    public function handle(): int
    {
        $cutoff = now()->subHours((int) $this->option('hours'));

        $query = User::query()
            ->where('role', UserRole::Customer)
            ->whereNull('email_verified_at')
            ->where('created_at', '<', $cutoff);

        foreach ($this->activityRelations as $relation) {
            $query->doesntHave($relation);
        }

        $deleted = $query->get()
            ->each(fn (User $user) => $user->forceDelete())
            ->count();

        $this->info("Pruned {$deleted} abandoned, activity-free customer account(s).");

        return self::SUCCESS;
    }
}
