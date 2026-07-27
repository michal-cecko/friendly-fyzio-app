<?php

namespace App\Filament\Clusters\Provoz\Resources\Reservations\Schemas;

use App\Filament\Clusters\Provoz\Resources\Clients\ClientResource;
use App\Filament\Clusters\Provoz\Resources\Rooms\RoomResource;
use App\Filament\Clusters\Provoz\Resources\Services\ServiceResource;
use App\Filament\Clusters\Provoz\Resources\Users\UserResource;
use App\Filament\Support\Schemas\RecordTimestamps;
use App\Filament\Support\Schemas\ResponsiveColumns;
use App\Models\Reservation;
use App\Models\ReservationDocument;
use App\Support\Reservations\ConflictFinder;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
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
                // Conflict warning — a full-width danger callout shown only when
                // this reservation overlaps another sharing its room or therapist.
                ViewEntry::make('conflict_banner')
                    ->hiddenLabel()
                    ->columnSpanFull()
                    ->view('filament.infolists.reservation-conflict-banner')
                    ->visible(fn (Reservation $record): bool => ConflictFinder::forReservation($record) !== []),
                // Termín + účastníci merged into one section (date/time row above
                // the participants) so the detail isn't over-divided.
                Section::make('Termín a účastníci')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('reservation_date')->label('Datum')->date('d.m.Y'),
                            TextEntry::make('start_time')->label('Od')->time('H:i'),
                            // „Do" is when the therapist is free again, not when the
                            // client leaves — the break is part of the slot.
                            TextEntry::make('end_time')
                                ->label('Do')
                                ->state(fn (Reservation $record): string => $record->endsAtIncludingBreak()->format('H:i'))
                                ->helperText(fn (Reservation $record): ?string => $record->breakLabel()),
                        ]),
                        Grid::make(ResponsiveColumns::DENSE)->gridContainer()->schema([
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
                                ->url(fn (Reservation $record): ?string => $record->therapist?->user_id !== null
                                    ? UserResource::getUrl('view', ['record' => $record->therapist->user_id])
                                    : null),
                            TextEntry::make('room.name')
                                ->label('Místnost')
                                ->placeholder('—')
                                ->url(fn (Reservation $record): ?string => $record->room !== null
                                    ? RoomResource::getUrl('view', ['record' => $record->room])
                                    : null),
                        ]),
                        RecordTimestamps::entries(),
                    ]),
                Section::make('Stav')
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->columnSpanFull()
                    ->gridContainer()
                    ->columns(ResponsiveColumns::DENSE)
                    ->schema([
                        TextEntry::make('status')->label('Stav')->badge(),
                        TextEntry::make('payment_status')->label('Platba')->badge(),
                        TextEntry::make('settled_at')->label('Vybaveno v')->dateTime('d.m.Y H:i')->badge()->color('success')->placeholder('—'),
                        TextEntry::make('confirmed_by')->label('Potvrdil')->badge()->placeholder('—'),
                        TextEntry::make('confirmedBy.name')
                            ->label('Potvrdil (osoba)')
                            ->placeholder('—')
                            ->url(fn (Reservation $record): ?string => $record->confirmedBy !== null
                                ? UserResource::getUrl('view', ['record' => $record->confirmedBy])
                                : null),
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
                        TextEntry::make('doctor_note_resolved_at')
                            ->label('Vyřešeno v')
                            ->dateTime('d.m.Y H:i')
                            ->badge()
                            ->color('success')
                            ->placeholder('— dosud nevyřešeno'),
                        RepeatableEntry::make('doctorNoteDocuments')
                            ->label('Nahraná potvrzení')
                            ->placeholder('— klient zatím nic nenahrál')
                            ->columnSpanFull()
                            ->contained(false)
                            ->schema([
                                TextEntry::make('original_name')
                                    ->hiddenLabel()
                                    ->icon(Heroicon::OutlinedPaperClip)
                                    ->url(fn (ReservationDocument $record): string => $record->downloadUrl())
                                    ->openUrlInNewTab()
                                    ->helperText(fn (ReservationDocument $record): string => $record->sizeForHumans()
                                        .' · nahráno '.$record->created_at->format('d.m.Y H:i')),
                            ]),
                        TextEntry::make('doctor_note_hint')
                            ->hiddenLabel()
                            ->state('Storno poplatek je pozastaven do doručení potvrzení od lékaře. Klient jej nahrává v klientské zóně nebo odkazem z e-mailu. Vyřešte přes akci „Vyřešit storno (lékařské potvrzení)" — buď potvrzení přijměte a poplatek prominěte, nebo jej doúčtujte, pokud nedorazí.')
                            ->columnSpanFull()
                            ->color('gray'),
                    ]),
            ]);
    }
}
