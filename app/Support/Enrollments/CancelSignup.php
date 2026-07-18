<?php

namespace App\Support\Enrollments;

use App\Enums\BookingStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Enums\EmailTemplateKey;
use App\Enums\PaymentStatus;
use App\Models\CourseEnrollment;
use App\Models\OneTimeLessonBooking;
use App\Models\WorkshopRegistration;
use App\Notifications\EnrollmentTemplateNotification;
use Illuminate\Support\Facades\DB;

/**
 * The core cancellation of a course/lesson/workshop sign-up, shared by the
 * client zone ({@see CancelSignupAsClient}, which adds the cancellation-window
 * check) and the admin (which overrides the window). Withdraws any open payment
 * request and — through the sign-up observers — offers the freed spot to the
 * waitlist. No money moves automatically; refunds/credit are settled by hand.
 */
class CancelSignup
{
    public function __invoke(
        CourseEnrollment|OneTimeLessonBooking|WorkshopRegistration $signup,
        bool $notify = true,
        EmailTemplateKey $emailKey = EmailTemplateKey::EnrollmentCancelledByClient,
    ): void {
        DB::transaction(function () use ($signup): void {
            $signup->payments()
                ->whereIn('status', [PaymentStatus::Unpaid->value, PaymentStatus::Overdue->value])
                ->delete();

            // Fires the model observer, which promotes the waitlist.
            $signup->update([
                'status' => $signup instanceof CourseEnrollment
                    ? CourseEnrollmentStatus::Cancelled
                    : BookingStatus::Cancelled,
            ]);
        });

        $client = $signup->client;

        if ($notify && $client !== null && filled($client->email)) {
            $client->notify(new EnrollmentTemplateNotification($emailKey, [
                'jmeno' => EnrollmentEmailContext::firstName($client),
                ...EnrollmentEmailContext::offerTokens($this->offer($signup)),
            ]));
        }
    }

    protected function offer(CourseEnrollment|OneTimeLessonBooking|WorkshopRegistration $signup): mixed
    {
        return match (true) {
            $signup instanceof CourseEnrollment => $signup->series,
            $signup instanceof OneTimeLessonBooking => $signup->lesson,
            $signup instanceof WorkshopRegistration => $signup->workshop,
        };
    }
}
