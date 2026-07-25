<?php

namespace App\Support\Emails;

use App\Enums\EmailTemplateKey;
use App\Enums\PaymentStatus;
use App\Models\CourseEnrollment;
use App\Models\OneOffEventBooking;
use App\Notifications\EnrollmentTemplateNotification;
use App\Support\Enrollments\EnrollmentEmailContext;
use App\Support\Payments\PaymentEmailTokens;

/**
 * Resolves and dispatches the CMS enrollment e-mails for a manual (re)send from
 * the admin, across the two signup types (course enrollment, one-off event
 * booking). Reuses {@see EnrollmentEmailContext} for the token context and
 * {@see EnrollmentTemplateNotification} for delivery, so the client's User
 * receives the same rendered mail the automatic flows send.
 */
class EnrollmentEmailer
{
    /** Client-facing keys every signup type can resend. @var list<EmailTemplateKey> */
    private const SHARED_CLIENT_KEYS = [
        EmailTemplateKey::WaitlistJoined,
        EmailTemplateKey::WaitlistSpotAvailable,
        EmailTemplateKey::EnrollmentCancelledByClient,
        EmailTemplateKey::EnrollmentCancelledByClinic,
        EmailTemplateKey::EnrollmentAutoCancelled,
    ];

    /** @var list<EmailTemplateKey> */
    private const PAYMENT_KEYS = [
        EmailTemplateKey::PaymentReceived,
        EmailTemplateKey::PaymentOverdue,
        EmailTemplateKey::InvoiceIssued,
    ];

    /**
     * @return array<string, array<string, string>>
     */
    public static function templateGroups(CourseEnrollment|OneOffEventBooking $signup): array
    {
        $clientKeys = [
            self::receivedKey($signup),
            ...self::SHARED_CLIENT_KEYS,
            ...($signup instanceof OneOffEventBooking ? [
                EmailTemplateKey::LessonScheduleChanged,
            ] : []),
        ];

        $groups = ['Klient' => self::keyOptions($clientKeys)];

        if (self::hasUnpaidPayment($signup)) {
            $groups['Platba'] = self::keyOptions(self::PAYMENT_KEYS);
        }

        return $groups;
    }

    public static function send(CourseEnrollment|OneOffEventBooking $signup, EmailTemplateKey $key, ?CopyRecipients $copies = null): void
    {
        $extra = self::extraTokens($signup, $key);

        $signup->client?->notify(new EnrollmentTemplateNotification($key, self::context($signup, $extra), $copies));
    }

    private static function receivedKey(CourseEnrollment|OneOffEventBooking $signup): EmailTemplateKey
    {
        return $signup instanceof CourseEnrollment
            ? EmailTemplateKey::CourseEnrollmentReceived
            : EmailTemplateKey::EventBookingReceived;
    }

    /**
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    private static function context(CourseEnrollment|OneOffEventBooking $signup, array $extra): array
    {
        return $signup instanceof CourseEnrollment
            ? EnrollmentEmailContext::forEnrollment($signup, $extra)
            : EnrollmentEmailContext::forEventBooking($signup, $extra);
    }

    /**
     * @return array<string, string>
     */
    private static function extraTokens(CourseEnrollment|OneOffEventBooking $signup, EmailTemplateKey $key): array
    {
        if (in_array($key, self::PAYMENT_KEYS, true)) {
            $payment = $signup->payments()->where('status', PaymentStatus::Unpaid->value)->latest()->first();

            return $payment !== null ? PaymentEmailTokens::for($payment) : [];
        }

        return [];
    }

    /**
     * @param  list<EmailTemplateKey>  $keys
     * @return array<string, string>
     */
    private static function keyOptions(array $keys): array
    {
        return collect($keys)
            ->mapWithKeys(fn (EmailTemplateKey $key): array => [$key->value => $key->label()])
            ->all();
    }

    private static function hasUnpaidPayment(CourseEnrollment|OneOffEventBooking $signup): bool
    {
        return $signup->payments()->where('status', PaymentStatus::Unpaid->value)->exists();
    }
}
