<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Public/private visibility for one-time lessons and workshops — the lesson &
 * workshop counterpart of {@see CourseSeriesVisibility}. A Private offer is
 * hidden from the anonymous public archive and reports OfferState::Preparing on
 * every public surface; it opens only through its hidden pre-sale link
 * (offerStateForPresale) or to a logged-in customer.
 */
enum OfferVisibility: string implements HasColor, HasLabel
{
    case Public = 'public';
    case Private = 'private';

    public function getLabel(): string
    {
        return match ($this) {
            self::Public => 'Veřejný',
            self::Private => 'Soukromý',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Public => 'success',
            self::Private => 'gray',
        };
    }
}
