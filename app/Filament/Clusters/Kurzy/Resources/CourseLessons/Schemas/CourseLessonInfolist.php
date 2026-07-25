<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseLessons\Schemas;

use App\Filament\Clusters\Kurzy\Resources\Courses\CourseResource;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\CourseSeriesResource;
use App\Filament\Clusters\Provoz\Resources\Rooms\RoomResource;
use App\Filament\Clusters\Provoz\Resources\Users\UserResource;
use App\Filament\Support\Schemas\OccupancyEntry;
use App\Filament\Support\Schemas\RecordTimestamps;
use App\Models\CourseLesson;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CourseLessonInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Detaily')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('series.course.name')
                            ->label('Kurz')
                            ->placeholder('—')
                            ->url(fn (CourseLesson $record): ?string => $record->series?->course !== null
                                ? CourseResource::getUrl('view', ['record' => $record->series->course])
                                : null),
                        TextEntry::make('series.name')
                            ->label('Série')
                            ->placeholder('—')
                            ->url(fn (CourseLesson $record): ?string => $record->series !== null
                                ? CourseSeriesResource::getUrl('view', ['record' => $record->series])
                                : null),
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
                            ->placeholder('—')
                            ->url(fn (CourseLesson $record): ?string => $record->instructor !== null
                                ? UserResource::getUrl('view', ['record' => $record->instructor])
                                : null),
                        TextEntry::make('room.name')
                            ->label('Místnost')
                            ->placeholder('—')
                            ->url(fn (CourseLesson $record): ?string => $record->room !== null
                                ? RoomResource::getUrl('view', ['record' => $record->room])
                                : null),
                        RecordTimestamps::entries(),
                    ]),
                Section::make('Obsazenost')
                    ->description('Kolik lidí se sejde na této lekci — přihlášení ze série, bez omluvených, plus náhradníci.')
                    ->columns(4)
                    ->schema([
                        OccupancyEntry::make(),
                        TextEntry::make('enrolled')
                            ->label('Přihlášeno')
                            ->state(fn (CourseLesson $record): int => $record->enrolledCount()),
                        TextEntry::make('excused')
                            ->label('Omluveno')
                            ->state(fn (CourseLesson $record): int => $record->excusedCount()),
                        TextEntry::make('substitutes')
                            ->label('Náhradníci')
                            ->state(fn (CourseLesson $record): int => $record->substitutesInCount()),
                    ]),
            ]);
    }
}
