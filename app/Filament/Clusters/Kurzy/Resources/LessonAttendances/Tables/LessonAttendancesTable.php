<?php

namespace App\Filament\Clusters\Kurzy\Resources\LessonAttendances\Tables;

use App\Filament\Support\Tables\TimestampColumns;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class LessonAttendancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('lesson.series.course.name')
                    ->label('Kurz')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('enrollment.client.name')
                    ->label('Klient')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('lesson.lesson_date')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable()
                    ->placeholder('—'),
                IconColumn::make('attended')
                    ->label('Účast')
                    ->boolean(),
                TextColumn::make('cancelled_at')
                    ->label('Zrušeno')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('token_generated')
                    ->label('Token')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                ...TimestampColumns::make(),
            ])
            ->filters([
                TernaryFilter::make('attended')
                    ->label('Účast'),
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
