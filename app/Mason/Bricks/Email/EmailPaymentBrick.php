<?php

namespace App\Mason\Bricks\Email;

use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * A payment box for the storno-fee e-mail: the QR-Platba image plus the amount, IBAN
 * and variable symbol. The values come from fixed {{ castka }}/{{ iban }}/{{ vs }} and
 * {{ qr }} tokens the renderer substitutes with the live payment + QR data-URI.
 */
class EmailPaymentBrick extends Brick
{
    public static function getId(): string
    {
        return 'email-payment';
    }

    public static function getLabel(): string
    {
        return 'Platební údaje';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedBanknotes;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('emails.bricks.payment', ['config' => $config])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                TextInput::make('title')
                    ->label('Nadpis boxu')
                    ->default('Platební údaje'),
                Toggle::make('show_qr')
                    ->label('Zobrazit QR platbu')
                    ->default(true),
            ]);
    }
}
