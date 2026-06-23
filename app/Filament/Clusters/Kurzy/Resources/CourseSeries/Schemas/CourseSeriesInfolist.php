<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseSeries\Schemas;

use App\Filament\Support\Schemas\RecordTimestampsSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CourseSeriesInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detaily')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('course.name')
                            ->label('Kurz')
                            ->placeholder('—'),
                        TextEntry::make('name')
                            ->label('Název'),
                        TextEntry::make('start_date')
                            ->label('Začátek')
                            ->date('d.m.Y')
                            ->placeholder('—'),
                        TextEntry::make('end_date')
                            ->label('Konec')
                            ->date('d.m.Y')
                            ->placeholder('—'),
                        TextEntry::make('capacity')
                            ->label('Kapacita')
                            ->placeholder('—'),
                        TextEntry::make('price')
                            ->label('Cena')
                            ->suffix(' Kč')
                            ->placeholder('—'),
                        TextEntry::make('status')
                            ->label('Stav')
                            ->badge(),
                    ]),
                RecordTimestampsSection::make(),
            ]);
    }
}
