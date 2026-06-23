<?php

namespace App\Filament\Clusters\Provoz\Resources\Clients\RelationManagers;

use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'clientNotes';

    protected static ?string $title = 'Poznámky z terapií';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedPencilSquare;

    /**
     * Notes are managed directly from the client View page.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('content')
                    ->label('Poznámka')
                    ->required()
                    ->rows(5)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('')
            ->columns([
                TextColumn::make('content')
                    ->label('Poznámka')
                    ->limit(80)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('author.name')
                    ->label('Autor')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Vytvořeno')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->toolbarActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['author_id'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
