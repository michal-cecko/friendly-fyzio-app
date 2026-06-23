<?php

namespace App\Mason\Bricks;

use App\Mason\Support\Fields;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class NewsletterBrick extends Brick
{
    public static function getId(): string
    {
        return 'newsletter';
    }

    public static function getLabel(): string
    {
        return 'Newsletter';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedEnvelope;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('bricks.newsletter', ['config' => $config])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                Fields::richText('title', 'Nadpis', required: true),
                Fields::richText('subtitle', 'Popis'),
                TextInput::make('placeholder')
                    ->label('Placeholder pole')
                    ->default('Váš e-mail'),
                TextInput::make('button_text')
                    ->label('Text tlačítka')
                    ->default('Odebírat'),
                TextInput::make('consent')
                    ->label('Text souhlasu')
                    ->default('Odesláním souhlasím se zpracováním osobních údajů.'),
            ]);
    }
}
