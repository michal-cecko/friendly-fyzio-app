<?php

namespace App\Mason\Bricks;

use App\Mason\Support\ButtonsField;
use App\Support\Mentions\StaffMentions;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
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
                TextInput::make('eyebrow')
                    ->label('Nadtitulek')
                    ->default('FriendlyFyzio'),
                TextInput::make('title')
                    ->label('Nadpis')
                    ->required(),
                RichEditor::make('features')
                    ->label('Odrážky')
                    ->mentions([StaffMentions::editorProvider()])
                    ->toolbarButtons([
                        ['bold', 'italic', 'link', 'bulletList', 'textColor'],
                    ]),
                MediaPicker::make('image')
                    ->label('Obrázek')
                    ->acceptedFileTypes(['image/*']),
                ButtonsField::make(),
            ]);
    }
}
