<?php

namespace App\Filament\Support\Tables;

use Filament\Tables\Columns\TextColumn;

class TimestampColumns
{
    /**
     * Reusable "created at" and "updated at" table columns, toggleable and
     * hidden by default, for consistent record metadata across resources.
     *
     * @return array<int, TextColumn>
     */
    public static function make(): array
    {
        return [
            TextColumn::make('created_at')
                ->label('Vytvořeno')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')
                ->label('Upraveno')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }
}
