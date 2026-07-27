<?php

namespace App\Filament\Clusters\Kurzy\Resources\LessonBookings\Schemas;

use App\Filament\Clusters\Kurzy\Resources\Lessons\LessonResource;
use App\Filament\Clusters\Provoz\Resources\Clients\ClientResource;
use App\Filament\Support\Schemas\RecordTimestamps;
use App\Models\LessonBooking;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LessonBookingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Detaily')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('lesson.name')
                            ->label('Akce')
                            ->placeholder('—')
                            ->url(fn (LessonBooking $record): ?string => $record->lesson !== null
                                ? LessonResource::getUrl('view', ['record' => $record->lesson])
                                : null),
                        TextEntry::make('lesson.lesson_date')
                            ->label('Datum')
                            ->date('d.m.Y')
                            ->placeholder('—'),
                        TextEntry::make('client.name')
                            ->label('Klient')
                            ->placeholder('—')
                            ->url(fn (LessonBooking $record): ?string => $record->client !== null
                                ? ClientResource::getUrl('view', ['record' => $record->client])
                                : null),
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
