<?php

namespace App\Filament\Clusters\Provoz\Resources\Specializations\Tables;

use App\Filament\Support\Tables\TimestampColumns;
use App\Models\Service;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SpecializationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Název')
                    ->searchable()
                    ->sortable(),
                // Editable straight from the list: mapping the whole catalogue is
                // one sitting, not one form per entry.
                SelectColumn::make('service_id')
                    ->label('Služba')
                    ->options(fn (): array => Service::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->selectablePlaceholder()
                    ->sortable(),
                TextColumn::make('therapist_specializations_count')
                    ->label('Terapeutů')
                    ->counts('therapistSpecializations')
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Popis')
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),
                ...TimestampColumns::make(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('service_id')
                    ->label('Služba')
                    ->relationship('service', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('mapped')
                    ->label('Přiřazená služba')
                    ->placeholder('Vše')
                    ->trueLabel('S přiřazenou službou')
                    ->falseLabel('Nezařazené')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('service_id'),
                        false: fn (Builder $query): Builder => $query->whereNull('service_id'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
