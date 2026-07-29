<?php

namespace App\Filament\Clusters\Kurzy\Resources\EventCategories\Schemas;

use App\Filament\Support\Schemas\RecordTimestamps;
use App\Models\EventCategory;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EventCategoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Detaily')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Název'),
                        TextEntry::make('slug')
                            ->label('URL název'),
                        TextEntry::make('description')
                            ->label('Popis')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('display_order')
                            ->label('Pořadí'),
                        TextEntry::make('cancel_before_hours')
                            ->label('Odhlášení z akce')
                            ->state(fn (EventCategory $record): string => $record->cancelBeforeHours().' hodin předem'
                                .($record->cancel_before_hours === null ? ' (z nastavení)' : '')),
                        TextEntry::make('published_at')
                            ->label('Publikováno')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('—'),
                        RecordTimestamps::entries(),
                    ]),
            ]);
    }
}
