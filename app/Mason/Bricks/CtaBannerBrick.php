<?php

namespace App\Mason\Bricks;

use App\Mason\Support\LinkPickerField;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class CtaBannerBrick extends Brick
{
    public static function getId(): string
    {
        return 'cta-banner';
    }

    public static function getLabel(): string
    {
        return 'Výzva k akci (pruh)';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedMegaphone;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('bricks.cta-banner', ['config' => $config])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                TextInput::make('eyebrow')
                    ->label('Nadtitulek'),
                TextInput::make('title')
                    ->label('Nadpis')
                    ->required(),
                Textarea::make('subtitle')
                    ->label('Popis')
                    ->rows(2),
                LinkPickerField::make('cta_', 'Hlavní tlačítko', withText: true),
                LinkPickerField::make('secondary_cta_', 'Vedlejší tlačítko', withText: true),
            ]);
    }
}
