<?php

namespace App\Filament\Support\Actions;

use App\Models\CourseSeries;
use App\Models\Lesson;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Js;

/**
 * Reveals (and mints on first open) the hidden sign-up link of a schedulable
 * offer — course series or one-off event. Whoever holds the link can sign up
 * even when the offer isn't publicly open, so it covers both pre-sale for stálí
 * klienti and invite-only runs. Full or ended offers stay closed even with it.
 * Shared across both offer resources and, like its sibling
 * {@see SendOfferInvitationAction}, only shown for a Private offer — a public
 * offer needs no hidden link because anyone can already sign up.
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
            ->visible(fn (CourseSeries|Lesson $record): bool => $record->isPrivate())
            ->modalHeading('Skrytý přihlašovací odkaz')
            ->modalDescription('Kdo dostane tento odkaz, může se přihlásit, i když termín není veřejně otevřený — hodí se pro předprodej i pro soukromé termíny jen na pozvánku. Plně obsazený nebo ukončený termín zůstává uzavřený i s odkazem.')
            ->modalIcon(Heroicon::OutlinedLink)
            ->schema(fn (CourseSeries|Lesson $record): array => [
                TextEntry::make('presale_url')
                    ->label('Odkaz')
                    ->state($record->presaleUrl())
                    ->copyable()
                    ->copyMessage('Odkaz zkopírován.'),
            ])
            ->extraModalFooterActions(fn (CourseSeries|Lesson $record): array => [
                Action::make('copyPresaleLink')
                    ->label('Kopírovat odkaz')
                    ->icon(Heroicon::OutlinedClipboardDocument)
                    ->color('primary')
                    // Clicking the link text copies it too, but a labelled button
                    // is the obvious target. The same clipboard call Filament's
                    // own ->copyable() emits; the URL is developer-built (route +
                    // random token), never user input.
                    ->alpineClickHandler(
                        'window.navigator.clipboard.writeText('.Js::from($record->presaleUrl()).'); '
                        .'$tooltip('.Js::from('Odkaz zkopírován.').', { theme: $store.theme, timeout: 2000 })'
                    ),
            ])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Zavřít');
    }
}
