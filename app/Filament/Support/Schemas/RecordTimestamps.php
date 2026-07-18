<?php

namespace App\Filament\Support\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Operation;
use Filament\Support\Icons\Heroicon;

class RecordTimestamps
{
    /**
     * A reusable row of "record metadata" entries showing when a record was
     * created, last updated and (only for soft-deleted records) deleted. Times
     * are shown relative (e.g. "před 2 dny") with the exact timestamp available
     * on hover.
     *
     * Meant to be appended into an existing section's schema as a quiet bottom
     * row, rather than living in its own standalone panel. Hidden on create
     * pages, where there are no timestamps yet.
     */
    public static function entries(): Grid
    {
        return Grid::make()
            ->gridContainer()
            ->columns(['default' => 1, '@md' => 2, '@2xl' => 4])
            ->columnSpanFull()
            ->hiddenOn(Operation::Create)
            ->schema([
                TextEntry::make('created_at')
                    ->label('Vytvořeno')
                    ->placeholder('—')
                    ->since()
                    ->dateTimeTooltip('d.m.Y H:i')
                    ->icon(Heroicon::OutlinedCalendarDays),
                TextEntry::make('updated_at')
                    ->label('Naposledy upraveno')
                    ->placeholder('—')
                    ->since()
                    ->dateTimeTooltip('d.m.Y H:i')
                    ->icon(Heroicon::OutlinedPencilSquare),
                TextEntry::make('deleted_at')
                    ->label('Smazáno')
                    ->placeholder('—')
                    ->since()
                    ->dateTimeTooltip('d.m.Y H:i')
                    ->icon(Heroicon::OutlinedTrash)
                    ->hidden(fn ($record) => blank($record?->deleted_at)),
            ]);
    }
}
