<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseCategories\Tables;

use App\Filament\Support\Tables\TimestampColumns;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CourseCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('display_order')
            ->columns([
                TextColumn::make('name')
                    ->label('Název')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('display_order')
                    ->label('Pořadí')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('courses_count')
                    ->label('Kurzů')
                    ->counts('courses'),
                TextColumn::make('published_at')
                    ->label('Publikováno')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                ...TimestampColumns::make(),
            ])
            ->filters([
                //
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
