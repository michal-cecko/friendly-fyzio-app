<?php

namespace App\Filament\Clusters\Obsah\Resources\Pages\Tables;

use App\Filament\Support\Actions\OpenPublicPageAction;
use App\Filament\Support\Tables\TimestampColumns;
use App\Models\Page;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('title')
                    ->label('Název')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('URL')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('published_at')
                    ->label('Stav')
                    ->badge()
                    ->state(fn (Page $record): string => $record->isPublished() ? 'Publikováno' : 'Koncept')
                    ->color(fn (Page $record): string => $record->isPublished() ? 'success' : 'gray')
                    ->sortable(),
                IconColumn::make('is_system')
                    ->label('Systémová')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label('Pořadí')
                    ->sortable(),
                ...TimestampColumns::make(),
            ])
            ->filters([
                TernaryFilter::make('published')
                    ->label('Publikováno')
                    ->placeholder('Vše')
                    ->trueLabel('Publikované')
                    ->falseLabel('Koncepty')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->published(),
                        false: fn (Builder $query): Builder => $query->where(
                            fn (Builder $q): Builder => $q->whereNull('published_at')->orWhere('published_at', '>', now()),
                        ),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TrashedFilter::make(),
            ])
            ->recordActions([
                OpenPublicPageAction::make(),
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (Page $record): bool => ! $record->is_system),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
