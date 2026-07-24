<?php

namespace App\Filament\Resources\ActivityLog\Tables;

use App\Filament\Resources\ActivityLog\ActivityLogResource;
use App\Models\User;
use App\Support\ActivityLog\ActivityPresenter;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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
                TextColumn::make('causer')
                    ->label('Kdo')
                    ->state(fn (Activity $record): string => ActivityPresenter::causerLabel($record)),
                TextColumn::make('summary')
                    ->label('Log')
                    ->state(fn (Activity $record): string => ActivityPresenter::summary($record))
                    ->color(fn (Activity $record): string => ActivityPresenter::eventColor($record->event))
                    ->wrap()
                    ->grow()
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where(fn (Builder $q): Builder => $q
                            ->where('description', 'like', "%{$search}%")
                            ->orWhere('subject_id', 'like', "%{$search}%")
                            ->orWhere('attribute_changes', 'like', "%{$search}%")
                            ->orWhere('properties', 'like', "%{$search}%"))),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->label('Akce')
                    ->multiple()
                    ->options(ActivityPresenter::eventOptions()),
                SelectFilter::make('subject_type')
                    ->label('Typ záznamu')
                    ->multiple()
                    ->options(fn (): array => Activity::query()
                        ->distinct()
                        ->orderBy('subject_type')
                        ->pluck('subject_type', 'subject_type')
                        ->filter()
                        ->map(fn (string $type): string => ActivityPresenter::subjectLabel($type))
                        ->all()),
                SelectFilter::make('causer_id')
                    ->label('Kdo')
                    ->searchable()
                    ->options(fn (): array => User::query()
                        ->whereIn('id', Activity::query()->whereNotNull('causer_id')->distinct()->pluck('causer_id'))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),
                TernaryFilter::make('system_causer')
                    ->label('Zdroj')
                    ->placeholder('Vše')
                    ->trueLabel('Systém / online')
                    ->falseLabel('Přihlášený uživatel')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNull('causer_id'),
                        false: fn (Builder $query): Builder => $query->whereNotNull('causer_id'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('created_from')->label('Od'),
                        DatePicker::make('created_until')->label('Do'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['created_from'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('created_at', '>=', $date))
                        ->when($data['created_until'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('created_at', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['created_from'] ?? null) {
                            $indicators[] = 'Od '.Carbon::parse($data['created_from'])->format('d.m.Y');
                        }

                        if ($data['created_until'] ?? null) {
                            $indicators[] = 'Do '.Carbon::parse($data['created_until'])->format('d.m.Y');
                        }

                        return $indicators;
                    }),
            ])
            ->deferFilters(false)
            ->recordUrl(fn (Activity $record): string => ActivityLogResource::getUrl('view', ['record' => $record]))
            ->recordActions([])
            ->toolbarActions([]);
    }
}
