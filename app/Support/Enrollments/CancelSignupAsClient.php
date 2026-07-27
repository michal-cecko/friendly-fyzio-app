<?php

namespace App\Support\Enrollments;

use App\Models\CourseEnrollment;
use App\Models\LessonBooking;
use App\Support\Settings;
use Illuminate\Support\Carbon;

/**
 * Client-initiated cancellation of a course/event sign-up from the
 * client zone (docs §4.1: "klient môže zrušiť do X dní pred začiatkom").
 * Each offer type has its own configurable window; past it the client has to
 * call the clinic. Cancelling withdraws any open payment request and — through
 * the sign-up observers — offers the freed spot to the waitlist.
 *
 * Paid sign-ups can still be cancelled in-window, but no money moves
 * automatically: the clinic settles refunds/credit by hand.
 */
class CancelSignupAsClient
{
    public function __construct(private CancelSignup $cancelSignup) {}

    public function __invoke(CourseEnrollment|LessonBooking $signup): void
    {
        if (! $this->isCancellable($signup)) {
            throw new CancellationWindowClosedException;
        }

        ($this->cancelSignup)($signup);
    }

    /**
     * Whether the client may still cancel this sign-up themselves: it must be
     * active and the offer must start beyond its configured cutoff.
     */
    public function isCancellable(CourseEnrollment|LessonBooking $signup): bool
    {
        if (! $this->isActive($signup)) {
            return false;
        }

        $deadline = $this->deadline($signup);

        return $deadline !== null && now()->lessThan($deadline);
    }

    /**
     * The moment self-cancellation closes for this sign-up.
     */
    public function deadline(CourseEnrollment|LessonBooking $signup): ?Carbon
    {
        return match (true) {
            $signup instanceof CourseEnrollment => $signup->series?->start_date
                ?->copy()
                ->startOfDay()
                ->subDays(Settings::courseCancelBeforeDays()),
            $signup instanceof LessonBooking => $signup->lesson
                ?->startsAt()
                ->subHours(Settings::eventCancelBeforeHours()),
        };
    }

    protected function isActive(CourseEnrollment|LessonBooking $signup): bool
    {
        return SignupStatus::isActive($signup);
    }
}
