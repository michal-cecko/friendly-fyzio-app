<?php

namespace App\Mason\Bricks;

use App\Mason\Support\Fields;
use App\Mason\Support\Icons;
use App\Mason\Support\LinkPickerField;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class FeatureCardsBrick extends Brick
{
    public static function getId(): string
    {
        return 'feature-cards';
    }

    public static function getLabel(): string
    {
        return 'Karty s ikonou';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedSquares2x2;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('bricks.feature-cards', ['config' => $config])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                ...Fields::heading(),
                Select::make('columns')
                    ->label('Počet sloupců')
                    ->options([2 => '2', 3 => '3', 4 => '4'])
                    ->default(3),
                Repeater::make('cards')
                    ->label('Karty')
                    ->schema([
                        Select::make('icon')
                            ->label('Ikona')
                            ->options(Icons::options())
                            ->searchable(),
                        TextInput::make('title')
                            ->label('Nadpis')
                            ->required(),
                        Textarea::make('description')
                            ->label('Popis')
                            ->rows(2),
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
