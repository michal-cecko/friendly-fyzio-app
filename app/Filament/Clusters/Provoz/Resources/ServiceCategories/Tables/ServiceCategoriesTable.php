<?php

namespace App\Filament\Clusters\Provoz\Resources\ServiceCategories\Tables;

use App\Enums\ServiceType;
use App\Filament\Support\Actions\OpenPublicPageAction;
use App\Filament\Support\Tables\TimestampColumns;
use App\Models\ServiceCategory;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ServiceCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Název')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('published_at')
                    ->label('Stav')
                    ->badge()
                    ->state(fn (ServiceCategory $record): string => $record->isPublished() ? 'Veřejné' : 'Skryté')
                    ->color(fn (ServiceCategory $record): string => $record->isPublished() ? 'success' : 'gray')
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Typ')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('services_count')
                    ->label('Počet služeb')
                    ->counts('services')
                    ->sortable(),
                ...TimestampColumns::make(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Typ')
                    ->options(ServiceType::class),
                TernaryFilter::make('published')
                    ->label('Publikováno')
                    ->placeholder('Vše')
                    ->trueLabel('Veřejné')
                    ->falseLabel('Skryté')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->published(),
                        false: fn (Builder $query): Builder => $query->where(
                            fn (Builder $q): Builder => $q->whereNull('published_at')->orWhere('published_at', '>', now()),
                        ),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordActions([
                OpenPublicPageAction::make(),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
