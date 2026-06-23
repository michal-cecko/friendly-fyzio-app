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

class CategoryCardsBrick extends Brick
{
    public static function getId(): string
    {
        return 'category-cards';
    }

    public static function getLabel(): string
    {
        return 'Kategorie se seznamem';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedQueueList;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('bricks.category-cards', ['config' => $config])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                ...Fields::heading(),
                TextInput::make('button_text')
                    ->label('Text tlačítka pod sekcí'),
                TextInput::make('button_url')
                    ->label('Odkaz tlačítka'),
                Repeater::make('categories')
                    ->label('Kategorie')
                    ->schema([
                        IconPicker::make('icon')
                            ->label('Ikona')
                            ->sets(['lucide'])
                            ->searchable()
                            ->default('lucide-activity'),
                        TextInput::make('title')
                            ->label('Nadpis')
                            ->required(),
                        TextInput::make('subtitle')
                            ->label('Podnadpis'),
                        TextInput::make('url')
                            ->label('Odkaz'),
                        Repeater::make('items')
                            ->label('Položky')
                            ->schema([
                                TextInput::make('label')
                                    ->label('Název')
                                    ->required(),
                                TextInput::make('meta')
                                    ->label('Doplňující údaj')
                                    ->placeholder('Začíná 12. 1. 2026'),
                                TextInput::make('url')
                                    ->label('Odkaz na detail'),
                            ])
                            ->columns(2)
                            ->defaultItems(0),
                    ])
                    ->defaultItems(0)
                    ->collapsed()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? 'Kategorie'),
            ]);
    }
}
