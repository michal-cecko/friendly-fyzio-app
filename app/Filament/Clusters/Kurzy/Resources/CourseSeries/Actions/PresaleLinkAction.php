<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseSeries\Actions;

use App\Models\CourseSeries;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\Icons\Heroicon;

/**
 * Reveals (and mints on first open) the hidden sign-up link of a series. Two
 * uses share one link: pre-sale ("predpredaj pre stálych klientov" — the URL
 * opens registration while the series is still Inactive, before public
 * launch) and invite-only private runs (visibility Soukromý), which never
 * appear on the web and take registrations only through this link.
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
            ->modalDescription('Kdo dostane tento odkaz, může se přihlásit, i když běh není veřejně otevřený — hodí se pro předprodej (běh ve stavu Neaktivní) i pro soukromé běhy jen na pozvánku. Plně obsazený nebo ukončený běh zůstává uzavřený i s odkazem.')
            ->modalIcon(Heroicon::OutlinedLink)
            ->schema(fn (CourseSeries $record): array => [
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
