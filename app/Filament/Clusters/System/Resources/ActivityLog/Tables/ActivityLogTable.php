<?php

namespace App\Filament\Clusters\System\Resources\ActivityLog\Tables;

use App\Filament\Clusters\System\Resources\ActivityLog\ActivityLogResource;
use App\Support\ActivityLog\ActivityPresenter;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ActivityLogTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Kdy')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('event')
                    ->label('Akce')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ActivityPresenter::eventLabel($state))
                    ->color(fn (?string $state): string => ActivityPresenter::eventColor($state)),
                TextColumn::make('subject_type')
                    ->label('Typ')
                    ->formatStateUsing(fn (?string $state): string => ActivityPresenter::subjectLabel($state)),
                TextColumn::make('subject_id')
                    ->label('Záznam')
                    ->state(fn (Activity $record): string => ActivityPresenter::subjectTitle($record))
                    ->tooltip(fn (Activity $record): ?string => $record->subject_id)
                    ->limit(40),
                TextColumn::make('causer')
                    ->label('Kdo')
                    ->state(fn (Activity $record): string => ActivityPresenter::causerLabel($record)),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->label('Akce')
                    ->options([
                        'created' => 'Vytvořeno',
                        'updated' => 'Upraveno',
                        'deleted' => 'Smazáno',
                        'restored' => 'Obnoveno',
                    ]),
                SelectFilter::make('subject_type')
                    ->label('Typ záznamu')
                    ->options(fn (): array => Activity::query()
                        ->distinct()
                        ->orderBy('subject_type')
                        ->pluck('subject_type', 'subject_type')
                        ->filter()
                        ->map(fn (string $type): string => ActivityPresenter::subjectLabel($type))
                        ->all()),
            ])
            ->recordUrl(fn (Activity $record): string => ActivityLogResource::getUrl('view', ['record' => $record]))
            ->recordActions([])
            ->toolbarActions([]);
    }
}
