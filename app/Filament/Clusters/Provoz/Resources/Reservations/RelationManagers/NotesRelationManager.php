<?php

namespace App\Filament\Clusters\Provoz\Resources\Reservations\RelationManagers;

use App\Models\Reservation;
use App\Support\Mentions\StaffMentions;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Therapy notes written in the context of this reservation. They belong to the
 * client (see ClientNote) and also appear in the "Poznámky z terapií" list on
 * the client profile, where notes without a reservation can be added too.
 */
class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'clientNotes';

    protected static ?string $title = 'Poznámky z terapií';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedPencilSquare;

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                RichEditor::make('content')
                    ->label('Poznámka')
                    ->required()
                    ->mentions([StaffMentions::editorProvider()])
                    ->toolbarButtons([
                        ['bold', 'italic', 'link', 'textColor'],
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('')
            ->modelLabel('poznámku')
            ->pluralModelLabel('poznámky')
            ->emptyStateHeading('Zatím žádné poznámky')
            ->emptyStateDescription('Přidejte první poznámku z terapie.')
            ->columns([
                TextColumn::make('content')
                    ->label('Poznámka')
                    ->formatStateUsing(fn (?string $state): string => str(strip_tags((string) $state))->squish()->toString())
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
                    ->modalHeading('Nová poznámka')
                    ->mutateFormDataUsing(function (array $data): array {
                        /** @var Reservation $reservation */
                        $reservation = $this->getOwnerRecord();

                        $data['author_id'] = auth()->id();
                        $data['client_id'] = $reservation->client_id;

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
