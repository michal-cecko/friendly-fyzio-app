<?php

namespace App\Filament\Clusters\Kurzy\Resources\Lessons\Schemas;

use App\Filament\Clusters\Kurzy\Resources\Courses\CourseResource;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\CourseSeriesResource;
use App\Filament\Clusters\Kurzy\Resources\EventCategories\EventCategoryResource;
use App\Filament\Clusters\Provoz\Resources\Rooms\RoomResource;
use App\Filament\Clusters\Provoz\Resources\Users\UserResource;
use App\Filament\Support\Schemas\OccupancyEntry;
use App\Filament\Support\Schemas\RecordTimestamps;
use App\Models\Lesson;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LessonInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Detaily')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Název')
                            ->state(fn (Lesson $record): string => $record->displayName()),
                        TextEntry::make('series.name')
                            ->label('Série')
                            ->placeholder('—')
                            ->url(fn (Lesson $record): ?string => $record->series !== null
                                ? CourseSeriesResource::getUrl('view', ['record' => $record->series])
                                : null),
                        TextEntry::make('slug')
                            ->label('URL název')
                            ->placeholder('—'),
                        TextEntry::make('category.name')
                            ->label('Kategorie')
                            ->badge()
                            ->placeholder('—')
                            ->url(fn (Lesson $record): ?string => $record->category !== null
                                ? EventCategoryResource::getUrl('view', ['record' => $record->category])
                                : null),
                        TextEntry::make('course.name')
                            ->label('Kurz')
                            ->state(fn (Lesson $record): ?string => $record->offerCourse()?->name)
                            ->placeholder('—')
                            ->url(fn (Lesson $record): ?string => $record->offerCourse() !== null
                                ? CourseResource::getUrl('view', ['record' => $record->offerCourse()])
                                : null),
                        TextEntry::make('instructor.name')
                            ->label('Lektor')
                            ->placeholder('—')
                            ->url(fn (Lesson $record): ?string => $record->instructor !== null
                                ? UserResource::getUrl('view', ['record' => $record->instructor])
                                : null),
                        TextEntry::make('room.name')
                            ->label('Místnost')
                            ->placeholder('—')
                            ->url(fn (Lesson $record): ?string => $record->room !== null
                                ? RoomResource::getUrl('view', ['record' => $record->room])
                                : null),
                        TextEntry::make('description')
                            ->label('Popis')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('lesson_date')
                            ->label('Datum')
                            ->date('d.m.Y')
                            ->placeholder('—'),
                        TextEntry::make('start_time')
                            ->label('Od')
                            ->time('H:i')
                            ->placeholder('—'),
                        TextEntry::make('end_time')
                            ->label('Do')
                            ->time('H:i')
                            ->placeholder('—'),
                        TextEntry::make('capacity')
                            ->label('Kapacita')
                            ->placeholder('—'),
                        TextEntry::make('waitlist_promotion_mode')
                            ->label('Uvolněné místo')
                            ->badge()
                            ->placeholder('—'),
                        TextEntry::make('waitlist_invited_until')
                            ->label('Místo drženo čekajícím do')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('—')
                            ->visible(fn (Lesson $record): bool => $record->waitlistInviteActive()),
                        TextEntry::make('price')
                            ->label('Cena')
                            ->suffix(' Kč')
                            ->placeholder('—'),
                        TextEntry::make('cancel_before_hours')
                            ->label('Odhlášení klientem')
                            ->state(fn (Lesson $record): string => $record->cancelBeforeHours().' hodin předem'
                                .match (true) {
                                    $record->cancel_before_hours !== null => '',
                                    $record->category?->cancel_before_hours !== null => ' (z kategorie)',
                                    default => ' (z nastavení)',
                                }),
                        TextEntry::make('published_at')
                            ->label('Publikováno')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('—'),
                        RecordTimestamps::entries(),
                    ]),
                Section::make('Obsazenost')
                    ->description('Kolik lidí se sejde na této lekci — přihlášení ze série, bez omluvených, plus náhradníci a jednorázové vstupy.')
                    ->columns(3)
                    ->schema([
                        OccupancyEntry::make(),
                        TextEntry::make('enrolled')
                            ->label('Přihlášeno')
                            ->state(fn (Lesson $record): int => $record->enrolledCount())
                            ->visible(fn (Lesson $record): bool => $record->isPartOfSeries()),
                        TextEntry::make('excused')
                            ->label('Omluveno')
                            ->state(fn (Lesson $record): int => $record->excusedCount())
                            ->visible(fn (Lesson $record): bool => $record->isPartOfSeries()),
                        TextEntry::make('substitutes')
                            ->label('Náhradníci')
                            ->state(fn (Lesson $record): int => $record->substitutesInCount())
                            ->visible(fn (Lesson $record): bool => $record->isPartOfSeries()),
                        TextEntry::make('dropIns')
                            ->label('Jednorázové vstupy')
                            ->state(fn (Lesson $record): int => $record->dropInCount()),
                        TextEntry::make('waitlist')
                            ->label('Čekací listina')
                            ->state(fn (Lesson $record): int => $record->waitlistEntries()->whereNull('notified_at')->count()),
                    ]),
            ]);
    }
}
