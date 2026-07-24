<?php

namespace App\Filament\Support\Schemas;

use App\Models\User;
use Filament\Schemas\Components\Callout;
use Filament\Support\Icons\Heroicon;

class DeactivatedBanner
{
    /**
     * A warning callout shown at the top of a User's detail/edit schema while
     * the account is deactivated, stating since when and that they cannot sign
     * in or book. Reads {@see User::deactivated_at}; hidden for active accounts.
     */
    public static function make(): Callout
    {
        return Callout::make('Účet je deaktivován')
            ->warning()
            ->icon(Heroicon::OutlinedLockClosed)
            ->description(fn (?User $record): ?string => $record?->isDeactivated()
                ? 'Deaktivováno '.$record->deactivated_at?->format('d.m.Y H:i').'. Účet se nemůže přihlásit do administrace ani klientské zóny a nemůže rezervovat online. Historie zůstává zachována kvůli záznamům — lze kdykoli reaktivovat.'
                : null)
            ->visible(fn (?User $record): bool => (bool) $record?->isDeactivated())
            ->columnSpanFull();
    }
}
