<?php

namespace App\Support\Enrollments;

use App\Console\Commands\CancelUnpaidEnrollments;
use App\Enums\BookingStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Enums\PaymentStatus;
use App\Models\CourseEnrollment;
use App\Models\LessonBooking;
use Illuminate\Database\Eloquent\Builder;

/**
 * Sign-ups still holding a spot they never paid for past the hold window.
 *
 * Shared by the {@see CancelUnpaidEnrollments} sweep and
 * by the Návrhy rule that surfaces the backlog while that sweep is switched off
 * before launch — so what the admin is told and what the command would cancel
 * can never drift apart.
 */
final class ExpiredPaymentHold
{
    /**
     * Constrain to sign-ups whose unpaid payment request is past its due date.
     *
     * @template TModel of CourseEnrollment|LessonBooking
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function scope(Builder $query): Builder
    {
        return $query->whereHas('payments', fn (Builder $payments) => $payments
            ->whereIn('status', [PaymentStatus::Unpaid->value, PaymentStatus::Overdue->value])
            ->whereDate('due_at', '<', today()));
    }

    /**
     * Course enrollments holding a spot in a série that has not ended yet.
     *
     * @return Builder<CourseEnrollment>
     */
    public static function enrollments(): Builder
    {
        return self::scope(
            CourseEnrollment::query()
                ->where('status', CourseEnrollmentStatus::Active)
                ->where('payment_status', '!=', PaymentStatus::Paid)
                ->whereHas('series', fn (Builder $series) => $series->whereDate('end_date', '>=', today()))
        );
    }

    /**
     * Single-lesson bookings holding a spot on a lesson still to come.
     *
     * @return Builder<LessonBooking>
     */
    public static function bookings(): Builder
    {
        return self::scope(
            LessonBooking::query()
                ->whereIn('status', BookingStatus::occupying())
                ->where('payment_status', '!=', PaymentStatus::Paid)
                ->whereHas('lesson', fn (Builder $lesson) => $lesson->whereDate('lesson_date', '>=', today()))
        );
    }
}
