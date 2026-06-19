<?php

namespace App\Mason\Bricks;

use App\Mason\Support\Fields;
use App\Mason\Support\LinkPickerField;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use RalphJSmit\Filament\MediaLibrary\Filament\Forms\Components\MediaPicker;

class InstagramBrick extends Brick
{
    public static function getId(): string
    {
        return 'instagram';
    }

    public static function getLabel(): string
    {
        return 'Instagram';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedCamera;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('bricks.instagram', ['config' => $config])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                ...Fields::heading(),
                TextInput::make('handle')
                    ->label('Instagram účet')
                    ->placeholder('@friendlyfyzio'),
                MediaPicker::make('images')
                    ->label('Obrázky')
                    ->multiple()
                    ->acceptedFileTypes(['image/*']),
                LinkPickerField::make('cta_', 'Tlačítko', withText: true),
            ]);
    }
}
