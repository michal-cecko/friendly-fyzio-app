<?php

namespace App\Observers;

use App\Enums\BookingStatus;
use App\Models\LessonAttendance;
use App\Models\LessonBooking;
use App\Support\Enrollments\PromoteFromWaitlist;
use Illuminate\Support\Str;

/**
 * Buying a seat puts you on the lesson's presence list, exactly like enrolling
 * in the série does — that is what lets a lecturer tick a drop-in off at the
 * door. Cancelling takes the seat back and offers it on.
 */
class LessonBookingObserver
{
    public function created(LessonBooking $booking): void
    {
        $this->seat($booking);
    }

    public function updated(LessonBooking $booking): void
    {
        if (! $booking->wasChanged('status')) {
            return;
        }

        if ($booking->status === BookingStatus::Cancelled) {
            $this->unseat($booking);
            PromoteFromWaitlist::handleAutomatic($booking->lesson);

            return;
        }

        $this->seat($booking);
    }

    public function deleted(LessonBooking $booking): void
    {
        $this->unseat($booking);
        PromoteFromWaitlist::handleAutomatic($booking->lesson);
    }

    /**
     * Written without model events, like the rest of the roster: the activity
     * log is for what people decide, not for bookkeeping.
     */
    protected function seat(LessonBooking $booking): void
    {
        if (! in_array($booking->status, BookingStatus::occupying(), true)) {
            return;
        }

        $seated = LessonAttendance::query()
            ->where('client_id', $booking->client_id)
            ->where('lesson_id', $booking->lesson_id)
            ->exists();

        // A client who is already on the list — enrolled in the série, or seated
        // by an earlier booking — keeps the seat they have rather than gaining
        // a second one.
        if ($seated) {
            return;
        }

        LessonAttendance::insertOrIgnore([
            'id' => (string) Str::uuid(),
            'client_id' => $booking->client_id,
            'enrollment_id' => null,
            'booking_id' => $booking->getKey(),
            'lesson_id' => $booking->lesson_id,
            'attended' => true,
            'cancelled_at' => null,
            'token_generated' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function unseat(LessonBooking $booking): void
    {
        LessonAttendance::query()
            ->where('booking_id', $booking->getKey())
            ->delete();
    }
}
