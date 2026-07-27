<?php

namespace App\Support\Enrollments;

use App\Enums\BookingStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Enums\EmailTemplateKey;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\CourseEnrollmentResource;
use App\Filament\Clusters\Kurzy\Resources\LessonBookings\LessonBookingResource;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\Lesson;
use App\Models\LessonBooking;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\ClientAccountCreatedNotification;
use App\Notifications\EnrollmentTemplateNotification;
use App\Support\Clients\ResolveCustomerAccount;
use App\Support\Payments\PaymentEmailTokens;
use App\Support\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Turns a validated public form submission into a persisted sign-up:
 * course-series enrollment or one-off event booking.
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
        [$enrollment, $payment, $client, $isNewAccount] = $this->locked('enrollment:series:'.$series->getKey(), function () use ($series, $data, $viaPresale): array {
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

    public function forEvent(Lesson $event, EnrollmentData $data, bool $viaPresale = false): LessonBooking
    {
        /** @var LessonBooking $booking */
        [$booking, $payment, $client, $isNewAccount] = $this->locked('lesson:'.$event->getKey(), function () use ($event, $data, $viaPresale): array {
            $event->refresh()->load(['course', 'category']);

            $state = $viaPresale ? $event->offerStateForPresale() : $event->offerState();

            if (! $state->acceptsRegistrations()) {
                throw new OfferClosedException;
            }

            [$client, $isNewAccount] = ResolveCustomerAccount::resolve($data->client, $data->name, $data->email, $data->phone);

            if ($event->bookings()->where('client_id', $client->id)->whereIn('status', BookingStatus::occupying())->exists()) {
                throw new AlreadySignedUpException;
            }

            $booking = $event->bookings()->create([
                'client_id' => $client->id,
                'status' => BookingStatus::Confirmed,
                'payment_status' => PaymentStatus::Unpaid,
                'note' => $data->note,
            ]);

            return [$booking, $this->paymentRequest($booking, $client, (int) $event->price), $client, $isNewAccount];
        });

        $this->notify(
            $client,
            $isNewAccount,
            new EnrollmentTemplateNotification(
                EmailTemplateKey::EventBookingReceived,
                EnrollmentEmailContext::forEventBooking($booking, PaymentEmailTokens::for($payment)),
            ),
            $event->instructor,
            EnrollmentEmailContext::offerTokens($event),
            $booking->note,
            LessonBookingResource::getUrl('view', ['record' => $booking]),
        );

        return $booking;
    }

    /**
     * The key is the full lock name. A lesson sign-up locks on `lesson:{id}`,
     * the same key {@see App\Support\Substitutes\RedeemToken} and
     * {@see App\Support\Substitutes\MoveClientToLesson} use — a drop-in buyer
     * and a náhrada redeemer compete for the same seat, so they must not hold
     * two different locks over it.
     *
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    protected function locked(string $key, callable $callback): mixed
    {
        return Cache::lock($key, 10)
            ->block(5, fn (): mixed => DB::transaction($callback));
    }

    /**
     * The unpaid QR payment request holding the spot: due when the configured
     * hold window runs out, after which the sign-up is auto-cancelled.
     */
    protected function paymentRequest(CourseEnrollment|LessonBooking $signup, User $client, int $amount): Payment
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
