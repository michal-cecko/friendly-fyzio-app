<?php

namespace App\Mason\Bricks;

use App\Mason\Support\LinkPickerField;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use RalphJSmit\Filament\MediaLibrary\Filament\Forms\Components\MediaPicker;

class HeroBrick extends Brick
{
    public static function getId(): string
    {
        return 'hero';
    }

    public static function getLabel(): string
    {
        return 'Hero sekce';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedPhoto;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('bricks.hero', ['config' => $config])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                TextInput::make('badge')
                    ->label('Štítek'),
                TextInput::make('title')
                    ->label('Nadpis')
                    ->required(),
                TextInput::make('title_accent')
                    ->label('Zvýrazněná část nadpisu'),
                Textarea::make('subtitle')
                    ->label('Podnadpis')
                    ->rows(3),
                MediaPicker::make('image')
                    ->label('Obrázek')
                    ->acceptedFileTypes(['image/*']),
                LinkPickerField::make('cta_', 'Hlavní tlačítko', withText: true),
                LinkPickerField::make('secondary_cta_', 'Vedlejší tlačítko', withText: true),
            ]);
    }
}
