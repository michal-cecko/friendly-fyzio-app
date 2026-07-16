<?php

namespace App\Filament\Clusters\Provoz\Resources\ServiceCategories\RelationManagers;

use App\Enums\ServiceVisibility;
use App\Filament\Clusters\Provoz\Resources\Services\ServiceResource;
use App\Models\Service;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Services belonging to a category. Read/manage only — services carry a complex
 * Mason-based form, so create/view/edit link out to the full ServiceResource
 * rather than reproducing that form inline.
 */
class ServicesRelationManager extends RelationManager
{
    protected static string $relationship = 'services';

    protected static ?string $title = 'Služby';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedSparkles;

    public function table(Table $table): Table
    {
        return $table
            ->heading('')
            ->recordTitleAttribute('name')
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Název')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('exam_type')
                    ->label('Typ vyšetření')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('duration_minutes')
                    ->label('Délka')
                    ->suffix(' min')
                    ->sortable(),
                TextColumn::make('price')
                    ->label('Cena')
                    ->numeric()
                    ->suffix(' Kč')
                    ->sortable(),
                TextColumn::make('visibility')
                    ->label('Viditelnost')
                    ->badge()
                    ->sortable(),
                TextColumn::make('published_at')
                    ->label('Publikováno')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('visibility')
                    ->label('Viditelnost')
                    ->options(ServiceVisibility::class),
            ])
            ->headerActions([
                Action::make('createService')
                    ->label('Nová služba')
                    ->icon(Heroicon::OutlinedPlus)
                    ->url(ServiceResource::getUrl('create')),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Service $record): string => ServiceResource::getUrl('view', ['record' => $record])),
                EditAction::make()
                    ->url(fn (Service $record): string => ServiceResource::getUrl('edit', ['record' => $record])),
            ])
            ->toolbarActions([]);
    }
}
