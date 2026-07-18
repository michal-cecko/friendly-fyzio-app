<?php

namespace App\Filament\Resources\ContactInquiries\Schemas;

use App\Filament\Support\Schemas\RecordTimestamps;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContactInquiryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Odesílatel')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Jméno'),
                        TextEntry::make('email')
                            ->label('E-mail')
                            ->copyable()
                            ->url(fn ($record): string => 'mailto:'.$record->email),
                        TextEntry::make('phone')
                            ->label('Telefon')
                            ->placeholder('—')
                            ->copyable(),
                        TextEntry::make('status')
                            ->label('Stav')
                            ->badge(),
                    ]),
                Section::make('Zpráva')
                    ->schema([
                        TextEntry::make('message')
                            ->hiddenLabel()
                            ->prose()
                            ->columnSpanFull(),
                        RecordTimestamps::entries(),
                    ]),
            ]);
    }
}
