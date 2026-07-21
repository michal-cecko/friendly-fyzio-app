<?php

namespace App\Filament\Clusters\Kurzy\Resources\OneOffEventBookings\Schemas;

use App\Filament\Support\Schemas\RecordTimestamps;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OneOffEventBookingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detaily')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('event.name')
                            ->label('Akce')
                            ->placeholder('—'),
                        TextEntry::make('event.event_date')
                            ->label('Datum')
                            ->date('d.m.Y')
                            ->placeholder('—'),
                        TextEntry::make('client.name')
                            ->label('Klient')
                            ->placeholder('—'),
                        TextEntry::make('status')
                            ->label('Stav')
                            ->badge(),
                        TextEntry::make('payment_status')
                            ->label('Platba')
                            ->badge(),
                        TextEntry::make('paid_at')
                            ->label('Zaplaceno')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('—'),
                        RecordTimestamps::entries(),
                    ]),
            ]);
    }
}
