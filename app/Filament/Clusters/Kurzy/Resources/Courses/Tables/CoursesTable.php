<?php

namespace App\Filament\Clusters\Kurzy\Resources\Courses\Tables;

use App\Filament\Support\Tables\TimestampColumns;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CoursesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Název')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label('Kategorie')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('instructor.name')
                    ->label('Lektor')
                    ->toggleable(),
                TextColumn::make('series_count')
                    ->label('Běhů')
                    ->counts('series'),
                TextColumn::make('published_at')
                    ->label('Publikováno')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                ...TimestampColumns::make(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Kategorie')
                    ->relationship('category', 'name')
                    ->preload(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
