<?php

namespace App\Observers;

use App\Enums\BookingStatus;
use App\Models\WorkshopRegistration;
use App\Support\Enrollments\PromoteFromWaitlist;

/**
 * A spot freed by a cancelled (or removed) workshop registration is
 * immediately offered to the workshop waitlist.
 */
class WorkshopRegistrationObserver
{
    public function updated(WorkshopRegistration $registration): void
    {
        if ($registration->wasChanged('status') && $registration->status === BookingStatus::Cancelled) {
            PromoteFromWaitlist::handleAutomatic($registration->workshop);
        }
    }

    public function deleted(WorkshopRegistration $registration): void
    {
        PromoteFromWaitlist::handleAutomatic($registration->workshop);
    }
}
