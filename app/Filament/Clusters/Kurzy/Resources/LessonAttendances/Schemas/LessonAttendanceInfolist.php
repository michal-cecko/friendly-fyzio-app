<?php

namespace App\Filament\Clusters\Kurzy\Resources\LessonAttendances\Schemas;

use App\Filament\Clusters\Kurzy\Resources\Courses\CourseResource;
use App\Filament\Clusters\Provoz\Resources\Clients\ClientResource;
use App\Filament\Support\Schemas\RecordTimestamps;
use App\Models\LessonAttendance;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LessonAttendanceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Detaily')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('lesson.series.course.name')
                            ->label('Kurz')
                            ->placeholder('—')
                            ->url(fn (LessonAttendance $record): ?string => $record->lesson?->series?->course !== null
                                ? CourseResource::getUrl('view', ['record' => $record->lesson->series->course])
                                : null),
                        TextEntry::make('enrollment.client.name')
                            ->label('Klient')
                            ->placeholder('—')
                            ->url(fn (LessonAttendance $record): ?string => $record->enrollment?->client !== null
                                ? ClientResource::getUrl('view', ['record' => $record->enrollment->client])
                                : null),
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
                        RecordTimestamps::entries(),
                    ]),
            ]);
    }
}
