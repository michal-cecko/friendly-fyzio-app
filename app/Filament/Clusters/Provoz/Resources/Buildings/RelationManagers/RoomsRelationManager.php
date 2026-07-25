<?php

namespace App\Filament\Clusters\Provoz\Resources\Buildings\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RoomsRelationManager extends RelationManager
{
    protected static string $relationship = 'rooms';

    protected static ?string $title = 'Místnosti';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Název')
                    ->required()
                    ->maxLength(255),
                TextInput::make('short_name')
                    ->label('Zkratka')
                    ->helperText('Krátké označení pro kalendář, např. AV')
                    ->maxLength(16),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modelLabel('místnost')
            ->pluralModelLabel('místnosti')
            ->emptyStateHeading('Zatím žádné místnosti')
            ->emptyStateDescription('Přidejte první místnost této budovy.')
            ->columns([
                TextColumn::make('name')
                    ->label('Název')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('short_name')
                    ->label('Zkratka')
                    ->badge()
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->modalHeading('Nová místnost'),
            ])
            ->recordActions([
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
