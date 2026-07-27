<?php

namespace App\Filament\Support;

use App\Models\ClientNote;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared pieces of the "Poznámky z terapií" tables, which appear on the client,
 * the reservation and the staff-as-client detail pages.
 */
class ClientNotes
{
    /**
     * Notes are rich text of any length, so the table shows a short plain-text
     * preview — the full version, with formatting and mentions intact, is one
     * click away in the View modal.
     */
    public static function contentColumn(): TextColumn
    {
        return TextColumn::make('content')
            ->label('Poznámka')
            ->formatStateUsing(fn (?string $state): string => str(strip_tags((string) $state))->squish()->toString())
            ->limit(80)
            ->wrap()
            ->extraAttributes(['class' => 'max-w-[350px] sm:max-w-[500px]'])
            ->searchable();
    }

    public static function authorFilter(): SelectFilter
    {
        return SelectFilter::make('author_id')
            ->label('Autor')
            ->relationship('author', 'name', fn (Builder $query): Builder => $query->whereHas('authoredClientNotes'))
            ->searchable()
            ->preload();
    }

    /**
     * Read-only view of a single note, shown in the ViewAction modal.
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextEntry::make('content')
                    ->hiddenLabel()
                    ->html()
                    ->columnSpanFull(),
                TextEntry::make('author.name')
                    ->label('Autor')
                    ->placeholder('—'),
                TextEntry::make('reservation')
                    ->label('Rezervace')
                    ->state(fn (ClientNote $record): ?string => $record->reservation
                        ? collect([
                            $record->reservation->reservation_date?->format('d.m.Y'),
                            $record->reservation->service?->name,
                        ])->filter()->implode(' · ')
                        : null)
                    ->placeholder('—'),
                TextEntry::make('created_at')
                    ->label('Vytvořeno')
                    ->dateTime('d.m.Y H:i'),
            ]);
    }
}
