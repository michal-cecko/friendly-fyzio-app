<?php

namespace App\Filament\Support\Actions;

use App\Models\CourseSeries;
use App\Models\OneOffEvent;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\Icons\Heroicon;

/**
 * Reveals (and mints on first open) the hidden sign-up link of a schedulable
 * offer — course series or one-off event. Whoever holds the link can
 * sign up even when the offer isn't publicly open: pre-sale for stálí klienti and
 * invite-only Private runs both go through this one link. Full or ended offers
 * stay closed even with it. Shared across both offer resources.
 */
class PresaleLinkAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'presaleLink';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Přihlašovací odkaz')
            ->icon(Heroicon::OutlinedLink)
            ->color('gray')
            ->modalHeading('Skrytý přihlašovací odkaz')
            ->modalDescription('Kdo dostane tento odkaz, může se přihlásit, i když termín není veřejně otevřený — hodí se pro předprodej i pro soukromé termíny jen na pozvánku. Plně obsazený nebo ukončený termín zůstává uzavřený i s odkazem.')
            ->modalIcon(Heroicon::OutlinedLink)
            ->schema(fn (CourseSeries|OneOffEvent $record): array => [
                TextEntry::make('presale_url')
                    ->label('Odkaz')
                    ->state($record->presaleUrl())
                    ->copyable()
                    ->copyMessage('Odkaz zkopírován.'),
            ])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Zavřít');
    }
}
