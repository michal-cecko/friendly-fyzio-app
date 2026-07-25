<?php

namespace App\Support\Enrollments;

use App\Enums\WaitlistPromotionMode;
use App\Models\CourseSeries;
use App\Models\OneOffEvent;
use App\Models\WaitlistEntry;

/**
 * Fills freed spots from the waitlist (docs §4.4: "klient je automaticky
 * zaregistrovaný, ak sa niekto odhlási"): oldest pending entry first, an
 * unpaid sign-up + QR payment request is created for them and the "spot
 * available" e-mail goes out — first to pay wins; unpaid promotions expire
 * through the same hold-window auto-cancel as regular sign-ups, which frees
 * the spot for the next in line.
 *
 * The per-entry mechanics live in {@see OfferSpotToEntry}; this class only owns
 * the oldest-first, fill-until-full loop.
 */
class PromoteFromWaitlist
{
    /**
     * Dispatches a freed spot according to the offer's
     * {@see WaitlistPromotionMode}: sign the next in line up, open an invite
     * round, or leave it to staff. The admin actions call {@see handle()} and
     * {@see InviteWaitlistToSpot} directly, so they work in any mode.
     */
    public static function handleAutomatic(CourseSeries|OneOffEvent|null $offer): void
    {
        if ($offer === null) {
            return;
        }

        match ($offer->waitlistPromotionMode()) {
            WaitlistPromotionMode::AutomaticAdd => self::handle($offer),
            WaitlistPromotionMode::AutomaticInvite => app(InviteWaitlistToSpot::class)->handle($offer),
            WaitlistPromotionMode::Manual => null,
        };
    }

    public static function handle(CourseSeries|OneOffEvent $offer): void
    {
        $service = app(OfferSpotToEntry::class);

        // Never promote into a closed offer (ended series, past event, manual full).
        while (self::offerOpenForPromotion($offer) && $offer->spotsLeft() > 0) {
            /** @var WaitlistEntry|null $entry */
            $entry = $offer->waitlistEntries()->pending()->first();

            if ($entry === null) {
                return;
            }

            // Offered / Skipped both consume the entry, so the loop advances to the
            // next in line; only an unexpected failure stops the fill.
            if ($service->offer($offer, $entry) === OfferSpotResult::Failed) {
                return;
            }
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
}
