<?php

namespace App\Filament\Clusters\Provoz\Resources\Reservations\Schemas;

use App\Filament\Support\Schemas\RecordTimestampsSection;
use App\Filament\Support\Schemas\ResponsiveColumns;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ReservationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'lg' => 3])
                    ->columnSpanFull()
                    ->schema([
                        Section::make('Termín')
                            ->icon(Heroicon::OutlinedCalendarDays)
                            ->columnSpan(['default' => 'full', 'lg' => 2])
                            ->columns(3)
                            ->schema([
                                TextEntry::make('reservation_date')->label('Datum')->date('d.m.Y'),
                                TextEntry::make('start_time')->label('Od')->time('H:i'),
                                TextEntry::make('end_time')->label('Do')->time('H:i'),
                            ]),
                        RecordTimestampsSection::make()->collapsed(false),
                    ]),
                Grid::make(['default' => 1, 'lg' => 2])
                    ->columnSpanFull()
                    ->schema([
                        Section::make('Účastníci')
                            ->icon(Heroicon::OutlinedUsers)
                            ->columnSpan(['default' => 'full', 'lg' => 1])
                            ->gridContainer()
                            ->columns(ResponsiveColumns::DENSE)
                            ->schema([
                                TextEntry::make('client.name')->label('Klient')->placeholder('—'),
                                TextEntry::make('service.name')->label('Služba')->placeholder('—'),
                                TextEntry::make('therapist.user.name')->label('Terapeut')->placeholder('—'),
                                TextEntry::make('room.name')->label('Místnost')->placeholder('—'),
                            ]),
                        Section::make('Stav')
                            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                            ->columnSpan(['default' => 'full', 'lg' => 1])
                            ->gridContainer()
                            ->columns(ResponsiveColumns::DENSE)
                            ->schema([
                                TextEntry::make('status')->label('Stav')->badge(),
                                TextEntry::make('payment_status')->label('Platba')->badge(),
                                IconEntry::make('is_control_therapy')->label('Kontrolní terapie')->boolean(),
                                TextEntry::make('cancellation_reason')
                                    ->label('Důvod zrušení')
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                                TextEntry::make('notes')
                                    ->label('Poznámka')
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
