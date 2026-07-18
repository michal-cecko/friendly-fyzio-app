<?php

namespace App\Support\Enrollments;

use App\Enums\BookingStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Enums\EmailTemplateKey;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\CourseEnrollmentResource;
use App\Filament\Clusters\Lekce\Resources\OneTimeLessonBookings\OneTimeLessonBookingResource;
use App\Filament\Clusters\Workshopy\Resources\WorkshopRegistrations\WorkshopRegistrationResource;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\OneTimeLesson;
use App\Models\OneTimeLessonBooking;
use App\Models\Payment;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use App\Notifications\ClientAccountCreatedNotification;
use App\Notifications\EnrollmentTemplateNotification;
use App\Support\Clients\ResolveCustomerAccount;
use App\Support\Payments\PaymentEmailTokens;
use App\Support\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Turns a validated public form submission into a persisted sign-up:
 * course-series enrollment, one-time lesson booking or workshop registration.
 *
 * Booking is serialised per offer with a short cache lock and the offer state
 * is re-checked inside the transaction, so two people can't grab the last
 * spot. The sign-up starts unpaid with a QR payment request due in the
 * configured hold window ("místo je rezervováno 48 hodin"); e-mails go out
 * after commit: payment instructions to the client, a heads-up to the
 * instructor, and the account e-mail when a fresh customer account was made.
 */
class SignUpForOffer
{
    public function forSeries(CourseSeries $series, EnrollmentData $data, bool $viaPresale = false): CourseEnrollment
    {
        /** @var CourseEnrollment $enrollment */
        [$enrollment, $payment, $client, $isNewAccount] = $this->locked('series:'.$series->getKey(), function () use ($series, $data, $viaPresale): array {
            $series->refresh()->load('course');

            $state = $viaPresale ? $series->offerStateForPresale() : $series->offerState();

            if (! $state->acceptsRegistrations()) {
                throw new OfferClosedException;
            }

            [$client, $isNewAccount] = ResolveCustomerAccount::resolve($data->client, $data->name, $data->email, $data->phone);

            if ($series->enrollments()->where('client_id', $client->id)->where('status', CourseEnrollmentStatus::Active)->exists()) {
                throw new AlreadySignedUpException;
            }

            $enrollment = $series->enrollments()->create([
                'client_id' => $client->id,
                'status' => CourseEnrollmentStatus::Active,
                'payment_status' => PaymentStatus::Unpaid,
                'note' => $data->note,
            ]);

            return [$enrollment, $this->paymentRequest($enrollment, $client, $series->currentPrice()), $client, $isNewAccount];
        });

        $this->notify(
            $client,
            $isNewAccount,
            new EnrollmentTemplateNotification(
                EmailTemplateKey::CourseEnrollmentReceived,
                EnrollmentEmailContext::forEnrollment($enrollment, PaymentEmailTokens::for($payment)),
            ),
            $series->course?->instructor,
            EnrollmentEmailContext::offerTokens($series),
            $enrollment->note,
            CourseEnrollmentResource::getUrl('view', ['record' => $enrollment]),
        );

        return $enrollment;
    }

    public function forLesson(OneTimeLesson $lesson, EnrollmentData $data, bool $viaPresale = false): OneTimeLessonBooking
    {
        /** @var OneTimeLessonBooking $booking */
        [$booking, $payment, $client, $isNewAccount] = $this->locked('lesson:'.$lesson->getKey(), function () use ($lesson, $data, $viaPresale): array {
            $lesson->refresh()->load('course');

            $state = $viaPresale ? $lesson->offerStateForPresale() : $lesson->offerState();

            if (! $state->acceptsRegistrations()) {
                throw new OfferClosedException;
            }

            [$client, $isNewAccount] = ResolveCustomerAccount::resolve($data->client, $data->name, $data->email, $data->phone);

            if ($lesson->bookings()->where('client_id', $client->id)->whereIn('status', BookingStatus::occupying())->exists()) {
                throw new AlreadySignedUpException;
            }

            $booking = $lesson->bookings()->create([
                'client_id' => $client->id,
                'status' => BookingStatus::Confirmed,
                'payment_status' => PaymentStatus::Unpaid,
                'note' => $data->note,
            ]);

            return [$booking, $this->paymentRequest($booking, $client, (int) $lesson->price), $client, $isNewAccount];
        });

        $this->notify(
            $client,
            $isNewAccount,
            new EnrollmentTemplateNotification(
                EmailTemplateKey::LessonBookingReceived,
                EnrollmentEmailContext::forBooking($booking, PaymentEmailTokens::for($payment)),
            ),
            $lesson->instructor,
            EnrollmentEmailContext::offerTokens($lesson),
            $booking->note,
            OneTimeLessonBookingResource::getUrl('view', ['record' => $booking]),
        );

        return $booking;
    }

    public function forWorkshop(Workshop $workshop, EnrollmentData $data, bool $viaPresale = false): WorkshopRegistration
    {
        /** @var WorkshopRegistration $registration */
        [$registration, $payment, $client, $isNewAccount] = $this->locked('workshop:'.$workshop->getKey(), function () use ($workshop, $data, $viaPresale): array {
            $workshop->refresh();

            $state = $viaPresale ? $workshop->offerStateForPresale() : $workshop->offerState();

            if (! $state->acceptsRegistrations()) {
                throw new OfferClosedException;
            }

            [$client, $isNewAccount] = ResolveCustomerAccount::resolve($data->client, $data->name, $data->email, $data->phone);

            if ($workshop->registrations()->where('client_id', $client->id)->whereIn('status', BookingStatus::occupying())->exists()) {
                throw new AlreadySignedUpException;
            }

            $registration = $workshop->registrations()->create([
                'client_id' => $client->id,
                'status' => BookingStatus::Confirmed,
                'payment_status' => PaymentStatus::Unpaid,
                'note' => $data->note,
            ]);

            return [$registration, $this->paymentRequest($registration, $client, (int) $workshop->price), $client, $isNewAccount];
        });

        $this->notify(
            $client,
            $isNewAccount,
            new EnrollmentTemplateNotification(
                EmailTemplateKey::WorkshopRegistrationReceived,
                EnrollmentEmailContext::forRegistration($registration, PaymentEmailTokens::for($payment)),
            ),
            $workshop->instructor,
            EnrollmentEmailContext::offerTokens($workshop),
            $registration->note,
            WorkshopRegistrationResource::getUrl('view', ['record' => $registration]),
        );

        return $registration;
    }

    /**
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    protected function locked(string $key, callable $callback): mixed
    {
        return Cache::lock('enrollment:'.$key, 10)
            ->block(5, fn (): mixed => DB::transaction($callback));
    }

    /**
     * The unpaid QR payment request holding the spot: due when the configured
     * hold window runs out, after which the sign-up is auto-cancelled.
     */
    protected function paymentRequest(CourseEnrollment|OneTimeLessonBooking|WorkshopRegistration $signup, User $client, int $amount): Payment
    {
        return $signup->payments()->create([
            'client_id' => $client->id,
            'amount' => $amount,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Unpaid,
            'due_at' => now()->addHours(Settings::enrollmentHoldHours()),
        ]);
    }

    /**
     * Post-commit e-mails: payment instructions to the client, sign-up summary
     * to the instructor, account e-mail for a freshly created account.
     *
     * @param  array<string, string>  $offerTokens
     */
    protected function notify(
        User $client,
        bool $isNewAccount,
        EnrollmentTemplateNotification $clientNotification,
        ?User $instructor,
        array $offerTokens,
        ?string $note,
        string $adminUrl,
    ): void {
        $client->notify($clientNotification);

        $instructor?->notify(new EnrollmentTemplateNotification(EmailTemplateKey::TherapistEnrollmentCreated, [
            'jmeno' => EnrollmentEmailContext::firstName($instructor),
            ...$offerTokens,
            'klient' => $client->name,
            'telefon_klienta' => (string) ($client->phone ?? ''),
            'email_klienta' => (string) $client->email,
            'poznamka' => (string) ($note ?? '—'),
            'odkaz' => $adminUrl,
        ]));

        if ($isNewAccount) {
            $client->notify(new ClientAccountCreatedNotification);
        }
    }
}
