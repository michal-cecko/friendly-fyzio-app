<?php

namespace App\Observers;

use App\Enums\CourseSeriesStatus;
use App\Enums\CourseSeriesVisibility;
use App\Enums\EmailTemplateKey;
use App\Models\CourseSeries;
use App\Models\WaitlistEntry;
use App\Notifications\EnrollmentTemplateNotification;
use App\Support\Enrollments\EnrollmentEmailContext;
use Illuminate\Support\Facades\Notification;

/**
 * When a series opens for PUBLIC registration, everyone on the course's "chci
 * vědět první" interest list gets the announcement e-mail — each subscription
 * fires once. "Opens publicly" covers both the status flipping to Open and a
 * private (invite-only) run being switched to Veřejný while Open; private
 * series never announce.
 */
class CourseSeriesObserver
{
    public function updated(CourseSeries $series): void
    {
        if ($series->status !== CourseSeriesStatus::Open || $series->visibility !== CourseSeriesVisibility::Public) {
            return;
        }

        if ($series->wasChanged('status') || $series->wasChanged('visibility')) {
            $this->notifyInterestList($series);
        }
    }

    public function created(CourseSeries $series): void
    {
        if ($series->status === CourseSeriesStatus::Open && $series->visibility === CourseSeriesVisibility::Public) {
            $this->notifyInterestList($series);
        }
    }

    protected function notifyInterestList(CourseSeries $series): void
    {
        $course = $series->course;

        if ($course === null) {
            return;
        }

        $course->waitlistEntries()->pending()->get()
            ->each(function (WaitlistEntry $entry) use ($series, $course): void {
                $email = $entry->displayEmail();

                if ($email !== null) {
                    Notification::route('mail', $email)->notify(new EnrollmentTemplateNotification(
                        EmailTemplateKey::CourseRegistrationOpened,
                        [
                            'jmeno' => $entry->client !== null
                                ? EnrollmentEmailContext::firstName($entry->client)
                                : (string) str((string) $entry->name)->before(' '),
                            'kurz' => $course->name,
                            'beh' => $series->name,
                            'obdobi' => EnrollmentEmailContext::seriesPeriod($series),
                            'odkaz' => $course->permalink(),
                        ],
                    ));
                }

                $entry->forceFill(['notified_at' => now()])->save();
            });
    }
}
