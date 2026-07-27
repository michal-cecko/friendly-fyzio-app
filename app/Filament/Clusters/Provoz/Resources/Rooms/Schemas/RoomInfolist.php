<?php

namespace App\Filament\Clusters\Provoz\Resources\Rooms\Schemas;

use App\Filament\Clusters\Provoz\Resources\Services\ServiceResource;
use App\Filament\Support\Schemas\RecordTimestamps;
use App\Filament\Support\Schemas\ResponsiveColumns;
use App\Models\Room;
use App\Models\Service;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class RoomInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Místnost')
                    ->icon(Heroicon::OutlinedRectangleGroup)
                    ->gridContainer()
                    ->columns(ResponsiveColumns::DENSE)
                    ->schema([
                        TextEntry::make('name')->label('Název'),
                        TextEntry::make('short_name')->label('Zkratka')->badge()->placeholder('—'),
                        TextEntry::make('building.name')->label('Budova')->placeholder('—'),
                        TextEntry::make('reservations_count')
                            ->label('Rezervace')
                            ->state(fn (Room $record): int => $record->reservations()->count())
                            ->badge(),
                        RepeatableEntry::make('services')
                            ->label('Služby')
                            ->placeholder('Žádné služby se v této místnosti neposkytují')
                            ->columnSpanFull()
                            ->schema([
                                TextEntry::make('name')
                                    ->hiddenLabel()
                                    ->badge()
                                    ->url(fn (Service $record): string => ServiceResource::getUrl('view', ['record' => $record])),
                            ]),
                        RecordTimestamps::entries(),
                    ]),
            ]);
    }
}
