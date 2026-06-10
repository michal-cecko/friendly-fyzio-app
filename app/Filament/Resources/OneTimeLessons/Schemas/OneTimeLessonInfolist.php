<?php

namespace App\Filament\Resources\OneTimeLessons\Schemas;

use App\Filament\Support\Schemas\RecordTimestampsSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OneTimeLessonInfolist
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
                        TextEntry::make('instructor.name')
                            ->label('Lektor')
                            ->placeholder('—'),
                        TextEntry::make('room.name')
                            ->label('Místnost')
                            ->placeholder('—'),
                        TextEntry::make('lesson_date')
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
                    ]),
                RecordTimestampsSection::make(),
            ]);
    }
}
