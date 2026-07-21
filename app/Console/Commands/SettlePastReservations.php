<?php

namespace App\Console\Commands;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Console\Command;

/**
 * Marks reservations "Vybaveno" (settled) once nothing is left to do — a past
 * visit that is fully paid, or a cancelled storno whose fee has been paid. This
 * is the backstop for cases the PaymentObserver can't catch, where no payment
 * event fires as the visit passes: free (price 0) visits and prepaid bookings.
 * markSettledIfQualifies() is monotonic, so re-runs are harmless.
 */
class SettlePastReservations extends Command
{
    protected $signature = 'reservations:settle-past';

    protected $description = 'Označí vyřízené rezervace jako vybavené (proběhlé a uhrazené).';

    public function handle(): int
    {
        $settled = 0;

        Reservation::query()
            ->whereNull('settled_at')
            ->whereIn('status', [ReservationStatus::Confirmed, ReservationStatus::Cancelled])
            ->whereDate('reservation_date', '<=', today())
            ->with('service')
            ->get()
            ->each(function (Reservation $reservation) use (&$settled): void {
                if (! $reservation->qualifiesAsSettled()) {
                    return;
                }

                $reservation->markSettledIfQualifies();
                $settled++;
            });

        $this->info("Vybaveno rezervací: {$settled}");

        return self::SUCCESS;
    }
}
