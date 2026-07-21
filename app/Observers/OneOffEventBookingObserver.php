<?php

namespace App\Observers;

use App\Enums\BookingStatus;
use App\Models\OneOffEventBooking;
use App\Support\Enrollments\PromoteFromWaitlist;

/**
 * A spot freed by a cancelled (or removed) event booking is immediately
 * offered to the event's waitlist.
 */
class OneOffEventBookingObserver
{
    public function updated(OneOffEventBooking $booking): void
    {
        if ($booking->wasChanged('status') && $booking->status === BookingStatus::Cancelled) {
            PromoteFromWaitlist::handleAutomatic($booking->event);
        }
    }

    public function deleted(OneOffEventBooking $booking): void
    {
        PromoteFromWaitlist::handleAutomatic($booking->event);
    }
}
