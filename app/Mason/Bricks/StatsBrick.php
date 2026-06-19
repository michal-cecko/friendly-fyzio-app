<?php

namespace App\Mason\Bricks;

use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class StatsBrick extends Brick
{
    public static function getId(): string
    {
        return 'stats';
    }

    public static function getLabel(): string
    {
        return 'Statistiky';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedChartBar;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('bricks.stats', ['config' => $config])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                Repeater::make('stats')
                    ->label('Statistiky')
                    ->schema([
                        TextInput::make('value')
                            ->label('Hodnota')
                            ->required(),
                        TextInput::make('label')
                            ->label('Popisek')
                            ->required(),
                    ])
                    ->defaultItems(4)
                    ->reorderable()
                    ->grid(2)
                    ->itemLabel(fn (array $state): ?string => $state['value'] ?? 'Statistika'),
            ]);
    }
}
