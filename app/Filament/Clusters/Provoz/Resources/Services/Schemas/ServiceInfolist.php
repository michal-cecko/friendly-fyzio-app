<?php

namespace App\Filament\Clusters\Provoz\Resources\Services\Schemas;

use App\Filament\Clusters\Provoz\Resources\Rooms\RoomResource;
use App\Filament\Clusters\Provoz\Resources\ServiceCategories\ServiceCategoryResource;
use App\Filament\Clusters\Provoz\Resources\Users\UserResource;
use App\Filament\Support\Schemas\RecordTimestamps;
use App\Filament\Support\Schemas\ResponsiveColumns;
use App\Models\Room;
use App\Models\Service;
use App\Models\StaffProfile;
use Filament\Infolists\Components\RepeatableEntry;
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
                Grid::make()
                    ->columnSpanFull()
                    ->gridContainer()
                    ->columns(['default' => 1, '@3xl' => 2])
                    ->schema([
                        // Left column: each column is its own grid so the two sides
                        // stack independently (no shared row heights → no gap).
                        Grid::make(1)
                            ->schema([
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
                                            ->columnSpan(['default' => 1, 'lg' => 5])
                                            // Staff without category access (therapists, lecturers)
                                            // read the name but get no link they cannot open.
                                            ->url(fn (Service $record): ?string => $record->category !== null
                                                && ServiceCategoryResource::canView($record->category)
                                                ? ServiceCategoryResource::getUrl('view', ['record' => $record->category])
                                                : null),
                                        TextEntry::make('category.type')
                                            ->label('Typ')
                                            ->badge()
                                            ->placeholder('—')
                                            ->columnSpanFull(),
                                        TextEntry::make('description')
                                            ->label('Popis')
                                            ->html()
                                            ->placeholder('—')
                                            ->columnSpanFull(),
                                        RecordTimestamps::entries(),
                                    ]),
                                Section::make('Místnosti a terapeuti')
                                    ->icon(Heroicon::OutlinedBuildingOffice)
                                    ->gridContainer()
                                    ->columns(ResponsiveColumns::PAIR)
                                    ->schema([
                                        RepeatableEntry::make('rooms')
                                            ->label('Místnosti')
                                            ->schema([
                                                TextEntry::make('name')
                                                    ->hiddenLabel()
                                                    ->badge()
                                                    ->url(fn (Room $record): string => RoomResource::getUrl('view', ['record' => $record])),
                                            ]),
                                        RepeatableEntry::make('therapists')
                                            ->label('Terapeuti')
                                            ->schema([
                                                TextEntry::make('user.name')
                                                    ->hiddenLabel()
                                                    ->badge()
                                                    ->placeholder('—')
                                                    ->url(fn (StaffProfile $record): ?string => $record->user_id !== null
                                                        ? UserResource::getUrl('view', ['record' => $record->user_id])
                                                        : null),
                                            ]),
                                    ]),
                            ]),
                        // Right column: the detail sections stacked as a grid inside the grid.
                        Grid::make(1)
                            ->schema([
                                Section::make('Délka a cena')
                                    ->icon(Heroicon::OutlinedBanknotes)
                                    ->gridContainer()
                                    ->columns(ResponsiveColumns::DENSE)
                                    ->schema([
                                        TextEntry::make('duration_minutes')->label('Délka')->suffix(' min'),
                                        TextEntry::make('price')->label('Cena')->suffix(' Kč'),
                                    ]),
                                Section::make('Storno podmínky')
                                    ->icon(Heroicon::OutlinedClock)
                                    ->gridContainer()
                                    ->columns(ResponsiveColumns::PAIR)
                                    ->schema([
                                        TextEntry::make('cancellationRule.cancel_before_hours')
                                            ->label('Zrušit nejpozději (hodin předem)')
                                            ->placeholder('—'),
                                    ]),
                                Section::make('Viditelnost a publikování')
                                    ->icon(Heroicon::OutlinedEye)
                                    ->gridContainer()
                                    ->columns(ResponsiveColumns::PAIR)
                                    ->schema([
                                        TextEntry::make('visibility')->label('Viditelnost')->badge(),
                                        TextEntry::make('published_at')->label('Publikováno')->dateTime()->placeholder('—'),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
