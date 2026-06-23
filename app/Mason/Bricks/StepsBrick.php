<?php

namespace App\Mason\Bricks;

use App\Mason\Support\Fields;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Guava\IconPicker\Forms\Components\IconPicker;
use Illuminate\Contracts\Support\Htmlable;

class StepsBrick extends Brick
{
    public static function getId(): string
    {
        return 'steps';
    }

    public static function getLabel(): string
    {
        return 'Proces (kroky)';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedNumberedList;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('bricks.steps', ['config' => $config])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                ...Fields::heading(),
                Repeater::make('steps')
                    ->label('Kroky')
                    ->schema([
                        IconPicker::make('icon')
                            ->label('Ikona')
                            ->sets(['heroicons'])
                            ->searchable(),
                        TextInput::make('title')
                            ->label('Nadpis')
                            ->required(),
                        Fields::richText('description', 'Popis'),
                    ])
                    ->defaultItems(3)
                    ->reorderable()
                    ->collapsible()
                    ->collapsed()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? 'Krok'),
            ]);
    }
}
