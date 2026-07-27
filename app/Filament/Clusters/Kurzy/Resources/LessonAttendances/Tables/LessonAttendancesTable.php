<?php

namespace App\Filament\Clusters\Kurzy\Resources\LessonAttendances\Tables;

use App\Enums\LessonExcuseReason;
use App\Filament\Support\Actions\EditExcuseAction;
use App\Filament\Support\AttendancePresenter;
use App\Filament\Support\Tables\TimestampColumns;
use App\Models\LessonAttendance;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
                TextColumn::make('client.name')
                    ->label('Klient')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('lesson.lesson_date')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('origin')
                    ->label('Přihláška')
                    ->state(fn (LessonAttendance $record): string => AttendancePresenter::originLabel($record))
                    ->badge()
                    ->color(fn (LessonAttendance $record): string => AttendancePresenter::originColor($record)),
                IconColumn::make('presence')
                    ->label('Účast')
                    ->state(fn (LessonAttendance $record): bool => AttendancePresenter::isPresent($record))
                    ->icon(fn (LessonAttendance $record): Heroicon => AttendancePresenter::presenceIcon($record))
                    ->color(fn (LessonAttendance $record): string => AttendancePresenter::presenceColor($record)),
                TextColumn::make('cancelled_at')
                    ->label('Odhlášeno')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('excuse_reason')
                    ->label('Důvod')
                    ->badge()
                    ->tooltip(fn (LessonAttendance $record): ?string => $record->excuse_note)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('token_generated')
                    ->label('Poukaz')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                ...TimestampColumns::make(),
            ])
            ->filters([
                TernaryFilter::make('attended')
                    ->label('Účast'),
                SelectFilter::make('excuse_reason')
                    ->label('Důvod omluvy')
                    ->options(LessonExcuseReason::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditExcuseAction::make(),
            ]);
    }
}
