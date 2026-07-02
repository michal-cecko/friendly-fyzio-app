<?php

namespace App\Mason\Bricks;

use App\Mason\Support\LinkPickerField;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use RalphJSmit\Filament\MediaLibrary\Filament\Forms\Components\MediaPicker;

class LastMinuteBrick extends Brick
{
    public static function getId(): string
    {
        return 'last-minute';
    }

    public static function getLabel(): string
    {
        return 'Last-minute termíny';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedClock;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('bricks.last-minute', ['config' => $config])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                TextInput::make('eyebrow')
                    ->label('Nadtitulek')
                    ->default('Volné dnes a zítra'),
                TextInput::make('title')
                    ->label('Nadpis')
                    ->default('Last-minute termíny'),
                LinkPickerField::make('', 'Tlačítko', withText: true, withStyle: true, withColor: true, withIcon: true),
                Repeater::make('therapists')
                    ->label('Terapeuti')
                    ->schema([
                        MediaPicker::make('avatar')
                            ->label('Fotka')
                            ->acceptedFileTypes(['image/*']),
                        TextInput::make('name')
                            ->label('Jméno')
                            ->required(),
                        TextInput::make('role')
                            ->label('Specializace'),
                        Repeater::make('slots')
                            ->label('Volné termíny')
                            ->simple(TextInput::make('label')->required())
                            ->defaultItems(0),
                    ])
                    ->defaultItems(0)
                    ->collapsed()
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? 'Terapeut'),
            ]);
    }
}
