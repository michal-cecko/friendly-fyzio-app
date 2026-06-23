<?php

namespace App\Filament\Clusters\Obsah\Resources\Navigations\Schemas;

use App\Enums\NavigationLocation;
use App\Filament\Support\Schemas\RecordTimestampsSection;
use App\Models\Navigation;
use App\Models\Page;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class NavigationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('location')
                    ->label('Umístění')
                    ->options(NavigationLocation::class)
                    ->required()
                    ->hidden(fn (?Navigation $record): bool => $record !== null),

                Section::make('Položky menu')
                    ->schema([
                        Repeater::make('items')
                            ->hiddenLabel()
                            ->relationship()
                            ->orderColumn('display_order')
                            ->schema([
                                TextInput::make('label')
                                    ->label('Název')
                                    ->required(),
                                ...self::linkFields(),
                                Repeater::make('children')
                                    ->label('Pod-položky (rozbalovací menu)')
                                    ->relationship()
                                    ->orderColumn('display_order')
                                    ->schema([
                                        TextInput::make('label')
                                            ->label('Název')
                                            ->required(),
                                        ...self::linkFields(),
                                    ])
                                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                                    ->collapsed()
                                    ->defaultItems(0)
                                    ->columnSpanFull(),
                            ])
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                            ->collapsed()
                            ->reorderable()
                            ->columnSpanFull(),
                    ]),

                RecordTimestampsSection::make()
                    ->columns(['default' => 1, 'lg' => 2]),
            ]);
    }

    /**
     * @return array<int, mixed>
     */
    private static function linkFields(): array
    {
        return [
            Select::make('link_type')
                ->label('Typ odkazu')
                ->options(['page' => 'Stránka', 'custom' => 'Vlastní URL'])
                ->default('custom')
                ->live(),
            Select::make('page_id')
                ->label('Stránka')
                ->options(fn (): array => Page::query()->orderBy('title')->pluck('title', 'id')->all())
                ->searchable()
                ->visible(fn (Get $get): bool => $get('link_type') === 'page'),
            TextInput::make('url')
                ->label('URL')
                ->placeholder('https://… nebo /slug')
                ->visible(fn (Get $get): bool => ($get('link_type') ?? 'custom') === 'custom'),
            Select::make('target')
                ->label('Otevřít v')
                ->options(['_self' => 'Stejném okně', '_blank' => 'Novém okně'])
                ->default('_self'),
        ];
    }
}
