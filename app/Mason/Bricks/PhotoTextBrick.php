<?php

namespace App\Mason\Bricks;

use App\Mason\Support\ButtonsField;
use App\Mason\Support\Fields;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use RalphJSmit\Filament\MediaLibrary\Filament\Forms\Components\MediaPicker;

class PhotoTextBrick extends Brick
{
    public static function getId(): string
    {
        return 'photo-text';
    }

    public static function getLabel(): string
    {
        return 'Text s obrázkem';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedViewColumns;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('bricks.photo-text', ['config' => $config])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                MediaPicker::make('image')
                    ->label('Obrázek')
                    ->acceptedFileTypes(['image/*']),
                Select::make('image_position')
                    ->label('Pozice obrázku')
                    ->options(['left' => 'Vlevo', 'right' => 'Vpravo'])
                    ->default('left'),
                Fields::richText('title', 'Nadpis', required: true),
                RichEditor::make('body')
                    ->label('Text')
                    ->toolbarButtons([
                        ['bold', 'italic', 'link', 'bulletList', 'orderedList', 'textColor'],
                    ]),
                ButtonsField::make(),
            ]);
    }
}
