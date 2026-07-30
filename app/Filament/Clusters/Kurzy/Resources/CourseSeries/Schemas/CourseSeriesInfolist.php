<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseSeries\Schemas;

use App\Filament\Clusters\Kurzy\Resources\Courses\CourseResource;
use App\Filament\Support\Schemas\OccupancyEntry;
use App\Filament\Support\Schemas\RecordTimestamps;
use App\Models\CourseSeries;
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
                            ->placeholder('—')
                            ->url(fn (CourseSeries $record): ?string => $record->course !== null
                                ? CourseResource::getUrl('view', ['record' => $record->course])
                                : null),
                        TextEntry::make('name')
                            ->label('Název'),
                        TextEntry::make('instructor.name')
                            ->label('Lektor')
                            // An empty column means the course's own instructor teaches the série.
                            ->state(fn (CourseSeries $record): ?string => $record->leadInstructor()?->name)
                            ->helperText(fn (CourseSeries $record): ?string => $record->instructor_id === null
                                ? 'Převzato z kurzu'
                                : null)
                            ->placeholder('—'),
                        TextEntry::make('start_date')
                            ->label('Začátek')
                            ->date('d.m.Y')
                            ->placeholder('—'),
                        TextEntry::make('end_date')
                            ->label('Konec')
                            ->date('d.m.Y')
                            ->placeholder('—'),
                        TextEntry::make('schedule')
                            ->label('Rozvrh')
                            ->state(fn (CourseSeries $record): ?string => $record->scheduleLabel())
                            ->helperText(fn (CourseSeries $record): ?string => match (true) {
                                $record->hasLessonSchedule() => null,
                                $record->weeklySchedule()->isEmpty() => 'Bez rozvrhu se lekce přidávají po jedné',
                                default => 'Doplňte místnost u každého termínu, jinak lekce nejde vygenerovat',
                            })
                            ->placeholder('Nenastaveno'),
                        TextEntry::make('lessons')
                            ->label('Naplánováno lekcí')
                            ->state(fn (CourseSeries $record): int => $record->totalLessonsCount()),
                        TextEntry::make('capacity')
                            ->label('Kapacita')
                            ->placeholder('—'),
                        TextEntry::make('max_substitutions')
                            ->label('Max. náhrad')
                            ->helperText(fn (CourseSeries $record): ?string => $record->max_substitutions < 1
                                ? 'Náhrady nejsou povolené'
                                : null),
                        TextEntry::make('waitlist_promotion_mode')
                            ->label('Uvolněné místo')
                            ->badge(),
                        TextEntry::make('waitlist_invited_until')
                            ->label('Místo drženo čekajícím do')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('—')
                            ->visible(fn (CourseSeries $record): bool => $record->waitlistInviteActive()),
                        TextEntry::make('price')
                            ->label('Cena')
                            ->suffix(' Kč')
                            ->placeholder('—'),
                        TextEntry::make('status')
                            ->label('Stav')
                            ->badge(),
                        TextEntry::make('visibility')
                            ->label('Viditelnost')
                            ->badge(),
                        RecordTimestamps::entries(),
                    ]),
                Section::make('Obsazenost')
                    ->columns(3)
                    ->schema([
                        OccupancyEntry::make(),
                        TextEntry::make('waitlist')
                            ->label('Čekací listina')
                            ->state(fn (CourseSeries $record): int => $record->waitlistEntries()->whereNull('notified_at')->count()),
                    ]),
            ]);
    }
}
