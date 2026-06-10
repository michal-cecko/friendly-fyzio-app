<?php

namespace App\Filament\Resources\LessonAttendances\Schemas;

use App\Filament\Support\Schemas\RecordTimestampsSection;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LessonAttendanceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detaily')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('lesson.series.course.name')
                            ->label('Kurz')
                            ->placeholder('—'),
                        TextEntry::make('enrollment.client.name')
                            ->label('Klient')
                            ->placeholder('—'),
                        TextEntry::make('lesson.lesson_date')
                            ->label('Datum')
                            ->date('d.m.Y')
                            ->placeholder('—'),
                        IconEntry::make('attended')
                            ->label('Účast')
                            ->boolean(),
                        TextEntry::make('cancelled_at')
                            ->label('Zrušeno')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('—'),
                        IconEntry::make('token_generated')
                            ->label('Token vygenerován')
                            ->boolean(),
                    ]),
                RecordTimestampsSection::make(),
            ]);
    }
}
