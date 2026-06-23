<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseLessons\Tables;

use App\Filament\Support\Tables\TimestampColumns;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CourseLessonsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('lesson_date', 'desc')
            ->columns([
                TextColumn::make('series.course.name')
                    ->label('Kurz'),
                TextColumn::make('series.name')
                    ->label('Běh'),
                TextColumn::make('lesson_date')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('start_time')
                    ->label('Od'),
                TextColumn::make('end_time')
                    ->label('Do'),
                TextColumn::make('instructor.name')
                    ->label('Lektor')
                    ->toggleable(),
                TextColumn::make('room.name')
                    ->label('Místnost')
                    ->toggleable(),
                ...TimestampColumns::make(),
            ])
            ->filters([
                SelectFilter::make('series')
                    ->label('Běh')
                    ->relationship('series', 'name')
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
