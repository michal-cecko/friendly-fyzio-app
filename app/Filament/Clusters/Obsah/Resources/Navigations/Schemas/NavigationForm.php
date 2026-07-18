<?php

namespace App\Filament\Clusters\Obsah\Resources\Navigations\Schemas;

use App\Enums\NavigationLocation;
use App\Filament\Support\Schemas\PresenceBanner;
use App\Filament\Support\Schemas\RecordTimestamps;
use App\Mason\Support\LinkPickerField;
use App\Models\Navigation;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class NavigationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                PresenceBanner::make(),
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
                        RecordTimestamps::entries(),
                    ]),
            ]);
    }

    /**
     * @return array<int, mixed>
     */
    private static function linkFields(): array
    {
        return [
            LinkPickerField::make('', 'Odkaz', withTarget: true),
        ];
    }
}
