<?php

namespace App\Filament\Clusters\Provoz\Resources\Services\Schemas;

use App\Filament\Support\Schemas\RecordTimestampsSection;
use App\Filament\Support\Schemas\ResponsiveColumns;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ServiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                RecordTimestampsSection::firstRow(
                    Section::make('Základní údaje')
                        ->icon(Heroicon::OutlinedSparkles)
                        ->columns(['default' => 1, 'lg' => 12])
                        ->schema([
                        TextEntry::make('name')
                            ->label('Název')
                            ->columnSpan(['default' => 1, 'lg' => 7]),
                        TextEntry::make('category.name')
                            ->label('Kategorie')
                            ->placeholder('—')
                            ->columnSpan(['default' => 1, 'lg' => 5]),
                        TextEntry::make('category.type')
                            ->label('Typ')
                            ->badge()
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                ),
                Grid::make()
                    ->columnSpanFull()
                    ->gridContainer()
                    ->columns(['default' => 1, '@3xl' => 2])
                    ->schema([
                        Section::make('Délka a cena')
                            ->icon(Heroicon::OutlinedBanknotes)
                            ->gridContainer()
                            ->columns(ResponsiveColumns::DENSE)
                    ->schema([
                        TextEntry::make('duration_minutes')->label('Délka')->suffix(' min'),
                        TextEntry::make('break_minutes')->label('Pauza')->suffix(' min'),
                        TextEntry::make('price')->label('Cena')->suffix(' Kč'),
                    ]),
                Section::make('Viditelnost a publikování')
                    ->icon(Heroicon::OutlinedEye)
                    ->gridContainer()
                    ->columns(ResponsiveColumns::PAIR)
                    ->schema([
                        TextEntry::make('visibility')->label('Viditelnost')->badge(),
                        TextEntry::make('published_at')->label('Publikováno')->dateTime()->placeholder('—'),
                    ]),
                Section::make('Storno podmínky')
                    ->icon(Heroicon::OutlinedClock)
                    ->gridContainer()
                    ->columns(ResponsiveColumns::PAIR)
                    ->schema([
                        TextEntry::make('cancellationRule.cancel_before_hours')
                            ->label('Zrušit nejpozději (hodin předem)')
                            ->placeholder('—'),
                        TextEntry::make('cancellationRule.auto_cancel_after_days')
                            ->label('Automaticky zrušit po (dnech)')
                            ->placeholder('—'),
                    ]),
                        Section::make('Místnosti a terapeuti')
                            ->icon(Heroicon::OutlinedBuildingOffice)
                            ->gridContainer()
                            ->columns(ResponsiveColumns::PAIR)
                            ->schema([
                                TextEntry::make('rooms.name')->label('Místnosti')->badge()->placeholder('—'),
                                TextEntry::make('therapists.user.name')->label('Terapeuti')->badge()->placeholder('—'),
                            ]),
                    ]),
            ]);
    }
}
