<?php

namespace App\Models\Concerns;

use App\Enums\OfferState;
use App\Enums\OfferVisibility;
use App\Models\CourseSeries;
use Illuminate\Support\Str;

/**
 * Private/invite visibility + hidden pre-sale link for one-time lessons and
 * workshops, mirroring the bespoke methods on {@see CourseSeries}.
 * The using model must expose a `visibility` (OfferVisibility) attribute, a
 * `presale_token` column, and `permalink()` / `isPast()` / `isFull()`.
 */
trait HasPresaleAccess
{
    public function isPrivate(): bool
    {
        return $this->visibility === OfferVisibility::Private;
    }

    /**
     * Hidden-link state: the offer keeps taking registrations through its secret
     * link even while Private (invite-only). Past or full offers stay closed
     * even with the token.
     */
    public function offerStateForPresale(): OfferState
    {
        return match (true) {
            $this->isPast() => OfferState::Inactive,
            $this->isFull() => OfferState::Full,
            default => OfferState::Open,
        };
    }

    public function ensurePresaleToken(): string
    {
        if (blank($this->presale_token)) {
            $this->forceFill(['presale_token' => Str::random(40)])->save();
        }

        return (string) $this->presale_token;
    }

    public function presaleUrl(): string
    {
        return $this->permalink().'?predprodej='.$this->ensurePresaleToken();
    }
}
