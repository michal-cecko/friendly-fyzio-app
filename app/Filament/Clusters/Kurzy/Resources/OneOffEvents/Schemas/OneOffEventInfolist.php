<?php

namespace App\Filament\Clusters\Kurzy\Resources\OneOffEvents\Schemas;

use App\Filament\Clusters\Kurzy\Resources\Courses\CourseResource;
use App\Filament\Clusters\Kurzy\Resources\EventCategories\EventCategoryResource;
use App\Filament\Clusters\Provoz\Resources\Rooms\RoomResource;
use App\Filament\Clusters\Provoz\Resources\Users\UserResource;
use App\Filament\Support\Schemas\OccupancyEntry;
use App\Filament\Support\Schemas\RecordTimestamps;
use App\Models\OneOffEvent;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OneOffEventInfolist
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
                        TextEntry::make('category.name')
                            ->label('Kategorie')
                            ->badge()
                            ->placeholder('—')
                            ->url(fn (OneOffEvent $record): ?string => $record->category !== null
                                ? EventCategoryResource::getUrl('view', ['record' => $record->category])
                                : null),
                        TextEntry::make('course.name')
                            ->label('Kurz')
                            ->placeholder('—')
                            ->url(fn (OneOffEvent $record): ?string => $record->course !== null
                                ? CourseResource::getUrl('view', ['record' => $record->course])
                                : null),
                        TextEntry::make('instructor.name')
                            ->label('Lektor')
                            ->placeholder('—')
                            ->url(fn (OneOffEvent $record): ?string => $record->instructor !== null
                                ? UserResource::getUrl('view', ['record' => $record->instructor])
                                : null),
                        TextEntry::make('room.name')
                            ->label('Místnost')
                            ->placeholder('—')
                            ->url(fn (OneOffEvent $record): ?string => $record->room !== null
                                ? RoomResource::getUrl('view', ['record' => $record->room])
                                : null),
                        TextEntry::make('description')
                            ->label('Popis')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('event_date')
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
                        TextEntry::make('waitlist_promotion_mode')
                            ->label('Uvolněné místo')
                            ->badge(),
                        TextEntry::make('waitlist_invited_until')
                            ->label('Místo drženo čekajícím do')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('—')
                            ->visible(fn (OneOffEvent $record): bool => $record->waitlistInviteActive()),
                        TextEntry::make('price')
                            ->label('Cena')
                            ->suffix(' Kč')
                            ->placeholder('—'),
                        TextEntry::make('published_at')
                            ->label('Publikováno')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('—'),
                        RecordTimestamps::entries(),
                    ]),
                Section::make('Obsazenost')
                    ->columns(3)
                    ->schema([
                        OccupancyEntry::make(),
                        TextEntry::make('waitlist')
                            ->label('Čekací listina')
                            ->state(fn (OneOffEvent $record): int => $record->waitlistEntries()->whereNull('notified_at')->count()),
                    ]),
            ]);
    }
}
