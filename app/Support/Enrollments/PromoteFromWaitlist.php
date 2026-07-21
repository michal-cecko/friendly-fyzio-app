<?php

namespace App\Support\Enrollments;

use App\Enums\BookingStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Enums\EmailTemplateKey;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\CourseSeries;
use App\Models\OneOffEvent;
use App\Models\Payment;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Notifications\ClientAccountCreatedNotification;
use App\Notifications\EnrollmentTemplateNotification;
use App\Support\Clients\ResolveCustomerAccount;
use App\Support\Payments\PaymentEmailTokens;
use App\Support\Reservations\DeactivatedClientException;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Fills freed spots from the waitlist (docs §4.4: "klient je automaticky
 * zaregistrovaný, ak sa niekto odhlási"): oldest pending entry first, an
 * unpaid sign-up + QR payment request is created for them and the "spot
 * available" e-mail goes out — first to pay wins; unpaid promotions expire
 * through the same hold-window auto-cancel as regular sign-ups, which frees
 * the spot for the next in line.
 */
class PromoteFromWaitlist
{
    /**
     * Automatic promotion triggered by a freed spot — a no-op unless the offer
     * has automatic waitlist promotion switched on. The manual "promote from
     * waitlist" admin action calls {@see handle()} directly and ignores the flag.
     */
    public static function handleAutomatic(CourseSeries|OneOffEvent|null $offer): void
    {
        if ($offer !== null && $offer->autoPromotesWaitlist()) {
            self::handle($offer);
        }
    }

    public static function handle(CourseSeries|OneOffEvent $offer): void
    {
        // Never promote into a closed offer (ended series, past event, manual full).
        while (self::offerOpenForPromotion($offer) && $offer->spotsLeft() > 0) {
            /** @var WaitlistEntry|null $entry */
            $entry = $offer->waitlistEntries()->pending()->first();

            if ($entry === null) {
                return;
            }

            try {
                [$signup, $payment, $client, $isNewAccount] = DB::transaction(function () use ($offer, $entry): array {
                    [$client, $isNewAccount] = ResolveCustomerAccount::resolve(
                        $entry->client,
                        (string) ($entry->displayName() ?: $entry->email),
                        (string) $entry->displayEmail(),
                        $entry->displayPhone(),
                    );

                    $signup = self::createSignup($offer, $client);

                    $payment = $signup->payments()->create([
                        'client_id' => $client->id,
                        'amount' => self::amountDue($offer),
                        'method' => PaymentMethod::Qr,
                        'status' => PaymentStatus::Unpaid,
                        'due_at' => now()->addHours(Settings::enrollmentHoldHours()),
                    ]);

                    $entry->forceFill([
                        'client_id' => $client->id,
                        'notified_at' => now(),
                    ])->save();

                    return [$signup, $payment, $client, $isNewAccount];
                });
            } catch (DeactivatedClientException|AlreadySignedUpException) {
                // A dead-end entry must not block the queue: consume it and move on.
                $entry->forceFill(['notified_at' => now()])->save();

                continue;
            } catch (Throwable $exception) {
                report($exception);

                return;
            }

            self::notifyPromoted($client, $offer, $payment, $isNewAccount);

            unset($signup);
        }
    }

    protected static function offerOpenForPromotion(CourseSeries|OneOffEvent $offer): bool
    {
        $offer->refresh();

        return match (true) {
            $offer instanceof CourseSeries => ! $offer->hasEnded(),
            $offer instanceof OneOffEvent => ! $offer->isPast(),
        };
    }

    protected static function createSignup(CourseSeries|OneOffEvent $offer, User $client): mixed
    {
        if ($offer instanceof CourseSeries) {
            if ($offer->enrollments()->where('client_id', $client->id)->where('status', CourseEnrollmentStatus::Active)->exists()) {
                throw new AlreadySignedUpException;
            }

            return $offer->enrollments()->create([
                'client_id' => $client->id,
                'status' => CourseEnrollmentStatus::Active,
                'payment_status' => PaymentStatus::Unpaid,
                'note' => 'Automaticky přihlášen z čekací listiny.',
            ]);
        }

        $relation = $offer->bookings();

        if ($relation->clone()->where('client_id', $client->id)->whereIn('status', BookingStatus::occupying())->exists()) {
            throw new AlreadySignedUpException;
        }

        return $relation->create([
            'client_id' => $client->id,
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
            'note' => 'Automaticky přihlášen z čekací listiny.',
        ]);
    }

    protected static function amountDue(CourseSeries|OneOffEvent $offer): int
    {
        return $offer instanceof CourseSeries ? $offer->currentPrice() : (int) $offer->price;
    }

    protected static function notifyPromoted(User $client, CourseSeries|OneOffEvent $offer, Payment $payment, bool $isNewAccount): void
    {
        $client->notify(new EnrollmentTemplateNotification(EmailTemplateKey::WaitlistSpotAvailable, [
            'jmeno' => EnrollmentEmailContext::firstName($client),
            ...EnrollmentEmailContext::offerTokens($offer),
            'rezervace_hodin' => (string) Settings::enrollmentHoldHours(),
            ...PaymentEmailTokens::for($payment),
        ]));

        if ($isNewAccount) {
            $client->notify(new ClientAccountCreatedNotification);
        }
    }
}
