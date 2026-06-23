<?php

namespace App\Mason\Bricks;

use App\Mason\Support\Fields;
use App\Mason\Support\LinkPickerField;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use RalphJSmit\Filament\MediaLibrary\Filament\Forms\Components\MediaPicker;

class CardsBrick extends Brick
{
    public static function getId(): string
    {
        return 'cards';
    }

    public static function getLabel(): string
    {
        return 'Karty s obrázkem';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedRectangleStack;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('bricks.cards', ['config' => $config])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                ...Fields::heading(),
                Select::make('background')
                    ->label('Pozadí')
                    ->options(['white' => 'Bílé', 'alt' => 'Světle růžové'])
                    ->default('white'),
                Select::make('columns')
                    ->label('Počet sloupců')
                    ->options([2 => '2', 3 => '3', 4 => '4'])
                    ->default(4),
                Repeater::make('cards')
                    ->label('Karty')
                    ->schema([
                        MediaPicker::make('image')
                            ->label('Obrázek')
                            ->acceptedFileTypes(['image/*']),
                        TextInput::make('title')
                            ->label('Nadpis')
                            ->required(),
                        Fields::richText('description', 'Popis'),
                        TextInput::make('link_text')
                            ->label('Text odkazu')
                            ->default('Zjistit více'),
                        LinkPickerField::make('', 'Odkaz'),
                    ])
                    ->defaultItems(3)
                    ->reorderable()
                    ->collapsible()
                    ->collapsed()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? 'Karta'),
            ]);
    }
}
