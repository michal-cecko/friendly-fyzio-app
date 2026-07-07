<?php

namespace App\Filament\Clusters\Obsah\Resources\EmailTemplates\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmailTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->label('Název')
                    ->weight('semibold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subject')
                    ->label('Předmět')
                    ->wrap()
                    ->color('gray'),
                TextColumn::make('updated_at')
                    ->label('Upraveno')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
