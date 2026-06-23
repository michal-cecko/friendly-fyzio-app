<?php

namespace App\Filament\Clusters\LekceWorkshopy\Resources\OneTimeLessons\Tables;

use App\Filament\Support\Tables\TimestampColumns;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OneTimeLessonsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('course.name')
                    ->label('Kurz')
                    ->sortable(),
                TextColumn::make('lesson_date')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('start_time')
                    ->label('Od'),
                TextColumn::make('instructor.name')
                    ->label('Lektor')
                    ->toggleable(),
                TextColumn::make('room.name')
                    ->label('Místnost')
                    ->toggleable(),
                TextColumn::make('capacity')
                    ->label('Kapacita'),
                TextColumn::make('price')
                    ->label('Cena')
                    ->suffix(' Kč'),
                TextColumn::make('bookings_count')
                    ->label('Rezervací')
                    ->counts('bookings'),
                ...TimestampColumns::make(),
            ])
            ->defaultSort('lesson_date', 'desc')
            ->filters([
                SelectFilter::make('course')
                    ->label('Kurz')
                    ->relationship('course', 'name')
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
