<?php

namespace App\Filament\Support\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Operation;
use Filament\Support\Icons\Heroicon;

class RecordTimestampsSection
{
    /**
     * A reusable, compact "record metadata" section showing when a record was
     * created and last updated. Times are shown relative (e.g. "před 2 dny")
     * with the exact timestamp available on hover.
     *
     * Rendered as a narrow side panel when placed in a multi-column grid (see
     * {@see self::firstRow()}); it falls back to full width in single-column
     * parents. Hidden on create pages, where there are no timestamps yet.
     */
    public static function make(string $heading = 'Záznam'): Section
    {
        return Section::make($heading)
            ->icon(Heroicon::OutlinedClock)
            ->collapsible()
            ->collapsed()
            ->hiddenOn(Operation::Create)
            ->columnSpan(['default' => 'full', 'lg' => 1])
            ->schema([
                // Container query: the fields flow in a row and wrap based on the
                // panel's own width (not the viewport), so they sit side by side
                // when there's room and stack when the panel is narrow.
                Grid::make()
                    ->gridContainer()
                    ->columns(['default' => 1, '@md' => 2, '@2xl' => 4])
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
                    ]),
            ]);
    }

    /**
     * Lay out the schema's primary section next to a narrow, expanded "Záznam"
     * panel on the right (stacking on smaller screens). On create pages the
     * panel is hidden and the main section keeps its 2/3 width.
     */
    public static function firstRow(Component $main): Grid
    {
        return Grid::make(['default' => 1, 'lg' => 3])
            ->columnSpanFull()
            ->schema([
                $main->columnSpan(['default' => 'full', 'lg' => 2]),
                self::make()->collapsed(false),
            ]);
    }
}
