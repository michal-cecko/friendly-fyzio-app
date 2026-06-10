<?php

namespace App\Filament\Support\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;

class RecordTimestampsSection
{
    /**
     * A reusable, collapsed "record metadata" section showing when a record was
     * created and last updated. Times are shown relative (e.g. "před 2 dny")
     * with the exact timestamp available on hover.
     */
    public static function make(string $heading = 'Záznam'): Section
    {
        return Section::make($heading)
            ->icon(Heroicon::OutlinedClock)
            ->collapsible()
            ->collapsed()
            ->columnSpanFull()
            ->columns(2)
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
            ]);
    }
}
