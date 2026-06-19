<?php

namespace App\Mason\Bricks;

use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
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
                TextInput::make('title')
                    ->label('Nadpis')
                    ->required(),
                Textarea::make('subtitle')
                    ->label('Popis')
                    ->rows(2),
                TextInput::make('button_text')
                    ->label('Text tlačítka')
                    ->default('Odebírat'),
            ]);
    }
}
