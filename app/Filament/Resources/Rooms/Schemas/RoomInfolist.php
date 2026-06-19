<?php

namespace App\Filament\Resources\Rooms\Schemas;

use App\Filament\Support\Schemas\RecordTimestampsSection;
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
            ->columns(1)
            ->components([
                Section::make('Místnost')
                    ->icon(Heroicon::OutlinedRectangleGroup)
                    ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
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
                    ]),
                RecordTimestampsSection::make(),
            ]);
    }
}
