<?php

namespace App\Mason\Bricks;

use App\Mason\Support\Fields;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use RalphJSmit\Filament\MediaLibrary\Filament\Forms\Components\MediaPicker;

class TestimonialsBrick extends Brick
{
    public static function getId(): string
    {
        return 'testimonials';
    }

    public static function getLabel(): string
    {
        return 'Reference';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedChatBubbleLeftRight;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('bricks.testimonials', ['config' => $config])->render();
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
                    ->default('alt'),
                Repeater::make('items')
                    ->label('Reference')
                    ->schema([
                        Textarea::make('quote')
                            ->label('Citace')
                            ->rows(3)
                            ->required(),
                        TextInput::make('author')
                            ->label('Jméno')
                            ->required(),
                        TextInput::make('role')
                            ->label('Role / popis'),
                        MediaPicker::make('avatar')
                            ->label('Fotka')
                            ->acceptedFileTypes(['image/*']),
                    ])
                    ->defaultItems(3)
                    ->reorderable()
                    ->collapsible()
                    ->collapsed()
                    ->itemLabel(fn (array $state): ?string => $state['author'] ?? 'Reference'),
            ]);
    }
}
