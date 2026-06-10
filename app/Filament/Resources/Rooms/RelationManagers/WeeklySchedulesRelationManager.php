<?php

namespace App\Filament\Resources\Rooms\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WeeklySchedulesRelationManager extends RelationManager
{
    protected static string $relationship = 'weeklySchedules';

    protected static ?string $title = 'Rozvrh terapeutů';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        // Read-only: therapist schedules are managed on the therapist side.
        return $table
            ->recordTitleAttribute('day_of_week')
            ->columns([
                TextColumn::make('therapist.user.name')
                    ->label('Terapeut')
                    ->searchable(),
                TextColumn::make('day_of_week')
                    ->label('Den')
                    ->badge(),
                TextColumn::make('week_type')
                    ->label('Týden')
                    ->badge(),
                TextColumn::make('start_time')
                    ->label('Od'),
                TextColumn::make('end_time')
                    ->label('Do'),
            ]);
    }
}
