<?php

namespace App\Filament\Clusters\Provoz\Resources\Users\RelationManagers;

use App\Models\User;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class InstructedLessonsRelationManager extends RelationManager
{
    protected static string $relationship = 'instructedLessons';

    protected static ?string $title = 'Lekce kurzů';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof User && $ownerRecord->isTherapist();
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['series.course', 'room']))
            ->columns([
                TextColumn::make('series.course.name')
                    ->label('Kurz'),
                TextColumn::make('lesson_date')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('start_time')
                    ->label('Od')
                    ->time('H:i'),
                TextColumn::make('end_time')
                    ->label('Do')
                    ->time('H:i'),
                TextColumn::make('room.name')
                    ->label('Místnost'),
            ])
            ->defaultSort('lesson_date', 'desc')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
