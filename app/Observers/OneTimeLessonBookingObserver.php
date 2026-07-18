<?php

namespace App\Observers;

use App\Enums\BookingStatus;
use App\Models\OneTimeLessonBooking;
use App\Support\Enrollments\PromoteFromWaitlist;

/**
 * A spot freed by a cancelled (or removed) lesson booking is immediately
 * offered to the lesson waitlist.
 */
class OneTimeLessonBookingObserver
{
    public function updated(OneTimeLessonBooking $booking): void
    {
        if ($booking->wasChanged('status') && $booking->status === BookingStatus::Cancelled) {
            PromoteFromWaitlist::handleAutomatic($booking->lesson);
        }
    }

    public function deleted(OneTimeLessonBooking $booking): void
    {
        PromoteFromWaitlist::handleAutomatic($booking->lesson);
    }
}
