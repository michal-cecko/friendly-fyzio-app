<?php

namespace App\Support\Enrollments;

use App\Enums\EmailTemplateKey;
use App\Enums\PaymentStatus;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\OneOffEvent;
use App\Models\OneOffEventBooking;
use App\Models\Payment;

/**
 * Resolves the "kdo dřív zaplatí" (who-is-faster-to-pay) race created when a spot
 * is over-invited from the waitlist: several people are offered the same few
 * spots and hold an unpaid sign-up simultaneously. As soon as an offer fills up
 * by *paid* count, everyone still holding an unpaid over-invite is closed out —
 * their spot went to a faster payer.
 *
 * A non-over-booked offer has as many active takers as paid ones at the moment
 * it fills, so nothing is cancelled: the "full by paid" gate makes this a no-op
 * outside an actual race, which is why no separate "race" flag is needed.
 */
class CloseOverInvitedLosers
{
    public function __construct(private CancelSignup $cancelSignup) {}

    public function afterPayment(Payment $payment): void
    {
        $payable = $payment->payable;

        $offer = match (true) {
            $payable instanceof CourseEnrollment => $payable->series,
            $payable instanceof OneOffEventBooking => $payable->event,
            default => null,
        };

        if ($offer !== null) {
            $this->closeLosersFor($offer);
        }
    }

    public function closeLosersFor(CourseSeries|OneOffEvent $offer): void
    {
        $offer->refresh();

        if ($offer->paidTakers() < (int) $offer->capacity) {
            return;
        }

        $offer->activeTakers()
            ->where('payment_status', '!=', PaymentStatus::Paid->value)
            ->get()
            ->each(fn (CourseEnrollment|OneOffEventBooking $loser) => ($this->cancelSignup)(
                $loser,
                emailKey: EmailTemplateKey::EnrollmentAutoCancelled,
                reason: 'Místo bylo obsazeno jiným zájemcem z čekací listiny',
            ));
    }
}
