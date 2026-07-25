<?php

namespace App\Support\Enrollments;

use App\Enums\BookingStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Enums\EmailTemplateKey;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Concerns\HasCapacity;
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
 * Offers a spot to a *specific* waitlist entry: creates an unpaid sign-up + QR
 * payment request holding the spot for the hold window and sends the "spot
 * available" e-mail — first to pay wins, unpaid offers expire through the same
 * hold-window auto-cancel as any other sign-up.
 *
 * {@see PromoteFromWaitlist} loops this oldest-first to fill freed spots; the
 * admin "invite" action calls it for chosen entries, with {@see inviteMany()}'s
 * `$enforceCapacity` flag choosing between "reserve a spot" (bounded by
 * capacity) and "kdo dřív zaplatí" (over-invite, first to pay wins).
 */
class OfferSpotToEntry
{
    /**
     * Offer a spot to one entry. Capacity is the caller's concern — this never
     * checks {@see HasCapacity::spotsLeft()}.
     */
    public function offer(CourseSeries|OneOffEvent $offer, WaitlistEntry $entry): OfferSpotResult
    {
        if ($entry->displayEmail() === null) {
            // A guest without an e-mail can't be resolved into an account. Consume
            // the entry so a promotion loop doesn't spin on it forever.
            $entry->forceFill(['notified_at' => now()])->save();

            return OfferSpotResult::Skipped;
        }

        try {
            [$payment, $client, $isNewAccount] = DB::transaction(function () use ($offer, $entry): array {
                [$client, $isNewAccount] = ResolveCustomerAccount::resolve(
                    $entry->client,
                    (string) ($entry->displayName() ?: $entry->email),
                    (string) $entry->displayEmail(),
                    $entry->displayPhone(),
                );

                $signup = $this->createSignup($offer, $client);

                $payment = $signup->payments()->create([
                    'client_id' => $client->id,
                    'amount' => $this->amountDue($offer),
                    'method' => PaymentMethod::Qr,
                    'status' => PaymentStatus::Unpaid,
                    'due_at' => now()->addHours(Settings::enrollmentHoldHours()),
                ]);

                $entry->forceFill([
                    'client_id' => $client->id,
                    'notified_at' => now(),
                ])->save();

                return [$payment, $client, $isNewAccount];
            });
        } catch (DeactivatedClientException|AlreadySignedUpException) {
            // A dead-end entry must not block the queue: consume it and move on.
            $entry->forceFill(['notified_at' => now()])->save();

            return OfferSpotResult::Skipped;
        } catch (Throwable $exception) {
            report($exception);

            return OfferSpotResult::Failed;
        }

        $this->notifyPromoted($client, $offer, $payment, $isNewAccount);

        return OfferSpotResult::Created;
    }

    /**
     * Offer spots to many entries. `$enforceCapacity` = true (hold mode) stops
     * once the offer is full; false (race mode) over-invites all of them.
     *
     * @param  iterable<WaitlistEntry>  $entries
     */
    public function inviteMany(CourseSeries|OneOffEvent $offer, iterable $entries, bool $enforceCapacity): InviteSummary
    {
        $offered = 0;
        $skippedFull = 0;
        $skippedDeadEnd = 0;
        $skippedNoEmail = 0;

        foreach ($entries as $entry) {
            if ($enforceCapacity && $offer->refresh()->spotsLeft() <= 0) {
                $skippedFull++;

                continue;
            }

            if ($entry->displayEmail() === null) {
                $skippedNoEmail++;

                continue;
            }

            match ($this->offer($offer, $entry)) {
                OfferSpotResult::Created => $offered++,
                OfferSpotResult::Skipped, OfferSpotResult::Failed => $skippedDeadEnd++,
            };
        }

        return new InviteSummary($offered, $skippedFull, $skippedDeadEnd, $skippedNoEmail);
    }

    protected function createSignup(CourseSeries|OneOffEvent $offer, User $client): mixed
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

    protected function amountDue(CourseSeries|OneOffEvent $offer): int
    {
        return $offer instanceof CourseSeries ? $offer->currentPrice() : (int) $offer->price;
    }

    protected function notifyPromoted(User $client, CourseSeries|OneOffEvent $offer, Payment $payment, bool $isNewAccount): void
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
