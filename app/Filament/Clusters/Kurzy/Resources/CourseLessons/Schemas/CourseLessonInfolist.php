<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseLessons\Schemas;

use App\Filament\Support\Schemas\RecordTimestampsSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CourseLessonInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detaily')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('series.course.name')
                            ->label('Kurz')
                            ->placeholder('—'),
                        TextEntry::make('series.name')
                            ->label('Běh')
                            ->placeholder('—'),
                        TextEntry::make('lesson_date')
                            ->label('Datum')
                            ->date('d.m.Y'),
                        TextEntry::make('start_time')
                            ->label('Od')
                            ->placeholder('—'),
                        TextEntry::make('end_time')
                            ->label('Do')
                            ->placeholder('—'),
                        TextEntry::make('instructor.name')
                            ->label('Lektor')
                            ->placeholder('—'),
                        TextEntry::make('room.name')
                            ->label('Místnost')
                            ->placeholder('—'),
                    ]),
                RecordTimestampsSection::make(),
            ]);
    }
}
