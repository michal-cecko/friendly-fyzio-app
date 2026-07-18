<?php

namespace App\Filament\Clusters\Lekce\Resources\OneTimeLessons\Schemas;

use App\Filament\Support\Schemas\RecordTimestampsSection;
use App\Models\OneTimeLesson;
use Filament\Infolists\Components\IconEntry;
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
                        IconEntry::make('auto_promote_waitlist')
                            ->label('Automatické přidávání z čekací listiny')
                            ->boolean(),
                        TextEntry::make('price')
                            ->label('Cena')
                            ->suffix(' Kč')
                            ->placeholder('—'),
                    ]),
                Section::make('Obsazenost')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('taken')
                            ->label('Obsazeno')
                            ->state(fn (OneTimeLesson $record): string => $record->takenSpots().' / '.$record->capacity),
                        TextEntry::make('spots_left')
                            ->label('Volná místa')
                            ->state(fn (OneTimeLesson $record): int => $record->spotsLeft()),
                        TextEntry::make('waitlist')
                            ->label('Čekací listina')
                            ->state(fn (OneTimeLesson $record): int => $record->waitlistEntries()->whereNull('notified_at')->count()),
                    ]),
                RecordTimestampsSection::make(),
            ]);
    }
}
