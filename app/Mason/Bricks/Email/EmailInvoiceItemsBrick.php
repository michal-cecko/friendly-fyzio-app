<?php

namespace App\Mason\Bricks\Email;

use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Data-driven invoice items table for the "invoice_issued" e-mail. The brick
 * itself only emits the {{ polozky_tabulka }} token slot — the sending
 * notification substitutes it with the server-rendered table (an HtmlString,
 * inserted raw by the renderer).
 */
class EmailInvoiceItemsBrick extends Brick
{
    public static function getId(): string
    {
        return 'email-invoice-items';
    }

    public static function getLabel(): string
    {
        return 'Položky faktury';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedListBullet;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('emails.bricks.invoice-items')->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                Placeholder::make('info')
                    ->label('')
                    ->content('Tabulka položek se doplní automaticky z faktury, ke které se e-mail odesílá. Blok nemá žádné nastavení.'),
            ]);
    }
}
