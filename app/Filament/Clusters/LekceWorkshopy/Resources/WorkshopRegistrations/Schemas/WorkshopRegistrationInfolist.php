<?php

namespace App\Filament\Clusters\LekceWorkshopy\Resources\WorkshopRegistrations\Schemas;

use App\Filament\Support\Schemas\RecordTimestampsSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WorkshopRegistrationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detaily')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('workshop.name')
                            ->label('Workshop')
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
                    ]),
                RecordTimestampsSection::make(),
            ]);
    }
}
