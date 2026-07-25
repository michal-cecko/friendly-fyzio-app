<?php

namespace App\Support\Reservations;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;

/**
 * Aggregate figures for the reservations admin table, derived from an already
 * filtered query. Kept free of Filament so it can be unit-tested directly.
 *
 * Every method clones the incoming builder, so the same base query (typically
 * the table's filtered `getPageTableQuery()`) can be reused across calls.
 */
class ReservationMetrics
{
    /**
     * Reservation counts keyed by status value.
     *
     * @param  Builder<Reservation>  $query
     * @return array<string, int> e.g. ['confirmed' => 3, 'pending' => 1, 'cancelled' => 0]
     */
    public static function statusCounts(Builder $query): array
    {
        $counts = [];

        foreach (ReservationStatus::cases() as $status) {
            $counts[$status->value] = $query->clone()->where('status', $status)->count();
        }

        return $counts;
    }

    /**
     * Actual money received: sum of paid payments attached to reservations in
     * the filtered set (integer CZK).
     *
     * @param  Builder<Reservation>  $query
     */
    public static function revenue(Builder $query): int
    {
        $ids = $query->clone()->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        return (int) Payment::query()
            ->where('payable_type', (new Reservation)->getMorphClass())
            ->whereIn('payable_id', $ids)
            ->where('status', PaymentStatus::Paid)
            ->sum('amount');
    }

    /**
     * Constrain to reservations still owing money (unpaid or overdue).
     * Shared with the `outstanding` table filter so the metric and the filtered
     * list it links to can never drift apart.
     *
     * @param  Builder<Reservation>  $query
     * @return Builder<Reservation>
     */
    public static function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('payment_status', [PaymentStatus::Unpaid, PaymentStatus::Overdue]);
    }

    /**
     * Constrain to reservations where the client promised a doctor's note that
     * has not been resolved. Shared with the `doctor_note_pending` filter.
     *
     * @param  Builder<Reservation>  $query
     * @return Builder<Reservation>
     */
    public static function scopeDoctorNotePending(Builder $query): Builder
    {
        return $query
            ->whereNotNull('doctor_note_requested_at')
            ->whereNull('doctor_note_resolved_at');
    }

    /**
     * Constrain to past confirmed visits that have not been settled
     * ("Vybaveno") — attended, but the obligation is still open.
     * Shared with the `unsettled_past` filter.
     *
     * @param  Builder<Reservation>  $query
     * @return Builder<Reservation>
     */
    public static function scopeUnsettledPast(Builder $query): Builder
    {
        return $query
            ->whereDate('reservation_date', '<', today())
            ->whereNull('settled_at')
            ->where('status', ReservationStatus::Confirmed);
    }

    /**
     * Outstanding obligations across the filtered set: how many reservations are
     * unpaid/overdue and the approximate amount owed.
     *
     * The amount sums each reservation's service price. This is an approximation
     * — a cancelled reservation technically owes only a storno fee — but it is an
     * acceptable headline figure and keeps this to a single aggregate query.
     *
     * @param  Builder<Reservation>  $query
     * @return array{count: int, amount: int}
     */
    public static function outstanding(Builder $query): array
    {
        return [
            'count' => self::scopeOutstanding($query->clone())->count(),
            'amount' => (int) self::scopeOutstanding($query->clone())
                ->join('services', 'services.id', '=', 'reservations.service_id')
                ->sum('services.price'),
        ];
    }

    /**
     * Reservations where the client promised a doctor's note that has not yet
     * been resolved (mirrors the `doctor_note_pending` table filter).
     *
     * @param  Builder<Reservation>  $query
     */
    public static function doctorNotePending(Builder $query): int
    {
        return self::scopeDoctorNotePending($query->clone())->count();
    }

    /**
     * Past confirmed visits that have not been settled ("Vybaveno") yet —
     * i.e. attended but the obligation is still open.
     *
     * @param  Builder<Reservation>  $query
     */
    public static function unsettledPast(Builder $query): int
    {
        return self::scopeUnsettledPast($query->clone())->count();
    }
}
