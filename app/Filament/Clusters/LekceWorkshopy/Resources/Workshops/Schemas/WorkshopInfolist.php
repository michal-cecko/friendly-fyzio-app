<?php

namespace App\Filament\Clusters\LekceWorkshopy\Resources\Workshops\Schemas;

use App\Filament\Support\Schemas\RecordTimestampsSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WorkshopInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detaily')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Název'),
                        TextEntry::make('slug')
                            ->label('Slug'),
                        TextEntry::make('instructor.name')
                            ->label('Lektor')
                            ->placeholder('—'),
                        TextEntry::make('room.name')
                            ->label('Místnost')
                            ->placeholder('—'),
                        TextEntry::make('description')
                            ->label('Popis')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('workshop_date')
                            ->label('Datum')
                            ->date('d.m.Y')
                            ->placeholder('—'),
                        TextEntry::make('start_time')
                            ->label('Od')
                            ->placeholder('—'),
                        TextEntry::make('end_time')
                            ->label('Do')
                            ->placeholder('—'),
                        TextEntry::make('capacity')
                            ->label('Kapacita')
                            ->placeholder('—'),
                        TextEntry::make('price')
                            ->label('Cena')
                            ->suffix(' Kč')
                            ->placeholder('—'),
                        TextEntry::make('published_at')
                            ->label('Publikováno')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('—'),
                    ]),
                RecordTimestampsSection::make(),
            ]);
    }
}
