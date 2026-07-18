<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseSeries\Schemas;

use App\Filament\Support\Schemas\RecordTimestamps;
use App\Models\CourseSeries;
use Filament\Infolists\Components\IconEntry;
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
                        IconEntry::make('auto_promote_waitlist')
                            ->label('Automatické přidávání z čekací listiny')
                            ->boolean(),
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
                        TextEntry::make('taken')
                            ->label('Obsazeno')
                            ->state(fn (CourseSeries $record): string => $record->takenSpots().' / '.$record->capacity),
                        TextEntry::make('spots_left')
                            ->label('Volná místa')
                            ->state(fn (CourseSeries $record): int => $record->spotsLeft()),
                        TextEntry::make('waitlist')
                            ->label('Čekací listina')
                            ->state(fn (CourseSeries $record): int => $record->waitlistEntries()->whereNull('notified_at')->count()),
                    ]),
            ]);
    }
}
