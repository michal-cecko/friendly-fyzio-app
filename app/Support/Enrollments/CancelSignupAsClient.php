<?php

namespace App\Support\Enrollments;

use App\Models\CourseEnrollment;
use App\Models\OneTimeLessonBooking;
use App\Models\WorkshopRegistration;
use App\Support\Settings;
use Illuminate\Support\Carbon;

/**
 * Client-initiated cancellation of a course/lesson/workshop sign-up from the
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

    public function __invoke(CourseEnrollment|OneTimeLessonBooking|WorkshopRegistration $signup): void
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
    public function isCancellable(CourseEnrollment|OneTimeLessonBooking|WorkshopRegistration $signup): bool
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
    public function deadline(CourseEnrollment|OneTimeLessonBooking|WorkshopRegistration $signup): ?Carbon
    {
        return match (true) {
            $signup instanceof CourseEnrollment => $signup->series?->start_date
                ?->copy()
                ->startOfDay()
                ->subDays(Settings::courseCancelBeforeDays()),
            $signup instanceof OneTimeLessonBooking => $signup->lesson
                ?->startsAt()
                ->subHours(Settings::lessonCancelBeforeHours()),
            $signup instanceof WorkshopRegistration => $signup->workshop
                ?->startsAt()
                ->subDays(Settings::workshopCancelBeforeDays()),
        };
    }

    protected function isActive(CourseEnrollment|OneTimeLessonBooking|WorkshopRegistration $signup): bool
    {
        return SignupStatus::isActive($signup);
    }
}
