<?php

namespace App\Filament\Clusters\Provoz\Resources\Rooms\Schemas;

use App\Filament\Support\Schemas\RecordTimestamps;
use App\Filament\Support\Schemas\ResponsiveColumns;
use App\Models\Room;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class RoomInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Místnost')
                    ->icon(Heroicon::OutlinedRectangleGroup)
                    ->gridContainer()
                    ->columns(ResponsiveColumns::DENSE)
                    ->schema([
                        TextEntry::make('name')->label('Název'),
                        TextEntry::make('building.name')->label('Budova')->placeholder('—'),
                        TextEntry::make('reservations_count')
                            ->label('Rezervace')
                            ->state(fn (Room $record): int => $record->reservations()->count())
                            ->badge(),
                        TextEntry::make('blockings_count')
                            ->label('Blokace')
                            ->state(fn (Room $record): int => $record->blockings()->count())
                            ->badge(),
                        TextEntry::make('services.name')
                            ->label('Služby')
                            ->badge()
                            ->placeholder('—')
                            ->columnSpanFull(),
                        RecordTimestamps::entries(),
                    ]),
            ]);
    }
}
