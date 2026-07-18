<?php

namespace App\Filament\Clusters\Provoz\Resources\Reservations\Schemas;

use App\Filament\Clusters\Provoz\Resources\Clients\ClientResource;
use App\Filament\Clusters\Provoz\Resources\Rooms\RoomResource;
use App\Filament\Clusters\Provoz\Resources\Services\ServiceResource;
use App\Filament\Clusters\Provoz\Resources\TherapistProfiles\TherapistProfileResource;
use App\Filament\Support\Schemas\RecordTimestampsSection;
use App\Filament\Support\Schemas\ResponsiveColumns;
use App\Models\Reservation;
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
                        // Termín + účastníci merged into one section (date/time row above
                        // the participants) so the detail isn't over-divided.
                        Section::make('Termín a účastníci')
                            ->icon(Heroicon::OutlinedCalendarDays)
                            ->columnSpan(['default' => 'full', 'lg' => 2])
                            ->schema([
                                Grid::make(3)->schema([
                                    TextEntry::make('reservation_date')->label('Datum')->date('d.m.Y'),
                                    TextEntry::make('start_time')->label('Od')->time('H:i'),
                                    TextEntry::make('end_time')->label('Do')->time('H:i'),
                                ]),
                                Grid::make(ResponsiveColumns::DENSE)->schema([
                                    TextEntry::make('client.name')
                                        ->label('Klient')
                                        ->placeholder('—')
                                        ->url(fn (Reservation $record): ?string => $record->client !== null
                                            ? ClientResource::getUrl('view', ['record' => $record->client])
                                            : null),
                                    TextEntry::make('service.name')
                                        ->label('Služba')
                                        ->placeholder('—')
                                        ->url(fn (Reservation $record): ?string => $record->service !== null
                                            ? ServiceResource::getUrl('view', ['record' => $record->service])
                                            : null),
                                    TextEntry::make('therapist.user.name')
                                        ->label('Terapeut')
                                        ->placeholder('—')
                                        ->url(fn (Reservation $record): ?string => $record->therapist !== null
                                            ? TherapistProfileResource::getUrl('edit', ['record' => $record->therapist])
                                            : null),
                                    TextEntry::make('room.name')
                                        ->label('Místnost')
                                        ->placeholder('—')
                                        ->url(fn (Reservation $record): ?string => $record->room !== null
                                            ? RoomResource::getUrl('view', ['record' => $record->room])
                                            : null),
                                ]),
                            ]),
                        RecordTimestampsSection::make()->collapsed(false),
                    ]),
                Section::make('Stav')
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->columnSpanFull()
                    ->gridContainer()
                    ->columns(ResponsiveColumns::DENSE)
                    ->schema([
                        TextEntry::make('status')->label('Stav')->badge(),
                        TextEntry::make('payment_status')->label('Platba')->badge(),
                        TextEntry::make('confirmed_by')->label('Potvrdil')->badge()->placeholder('—'),
                        TextEntry::make('confirmedBy.name')->label('Potvrdil (osoba)')->placeholder('—'),
                        TextEntry::make('confirmed_at')->label('Potvrzeno v')->dateTime('d.m.Y H:i')->placeholder('—'),
                        IconEntry::make('is_control_therapy')->label('Kontrolní terapie')->boolean(),
                        TextEntry::make('cancellation_reason')
                            ->label('Důvod zrušení')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('notes')
                            ->label('Poznámka')
                            ->html()
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                // Surfaced only when the client cancelled late and promised a
                // doctor's note (the storno fee is waived pending its delivery) —
                // staff need to see and follow up on it.
                Section::make('Storno – potvrzení od lékaře')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->columnSpanFull()
                    ->columns(2)
                    ->visible(fn (Reservation $record): bool => $record->doctor_note_requested_at !== null)
                    ->schema([
                        TextEntry::make('doctor_note_requested_at')
                            ->label('Klient přislíbil potvrzení')
                            ->dateTime('d.m.Y H:i')
                            ->badge()
                            ->color('warning'),
                        TextEntry::make('storno_fee')
                            ->label('Pozastavený storno poplatek')
                            ->state(fn (Reservation $record): string => number_format($record->stornoFee(), 0, ',', ' ').' Kč')
                            ->badge()
                            ->color('warning'),
                        TextEntry::make('doctor_note_hint')
                            ->hiddenLabel()
                            ->state('Storno poplatek je pozastaven do doručení potvrzení od lékaře. Pokud potvrzení nedorazí, doúčtujte poplatek přes „Vyžádat platbu".')
                            ->columnSpanFull()
                            ->color('gray'),
                    ]),
            ]);
    }
}
