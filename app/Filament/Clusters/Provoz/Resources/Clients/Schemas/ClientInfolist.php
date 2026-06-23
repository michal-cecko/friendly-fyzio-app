<?php

namespace App\Filament\Clusters\Provoz\Resources\Clients\Schemas;

use App\Filament\Support\Schemas\RecordTimestampsSection;
use App\Filament\Support\Schemas\ResponsiveColumns;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ClientInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                RecordTimestampsSection::firstRow(
                    Section::make('Osobní údaje')
                        ->icon(Heroicon::OutlinedUser)
                        ->gridContainer()
                        ->columns(ResponsiveColumns::DENSE)
                        ->schema([
                        TextEntry::make('name')->label('Jméno'),
                        TextEntry::make('email')
                            ->label('E-mail')
                            ->copyable()
                            ->icon(Heroicon::OutlinedEnvelope),
                        TextEntry::make('phone')->label('Telefon')->placeholder('—'),
                        TextEntry::make('clientProfile.date_of_birth')
                            ->label('Datum narození')
                            ->date('d.m.Y')
                            ->placeholder('—'),
                        TextEntry::make('clientProfile.address_city')->label('Město')->placeholder('—'),
                        TextEntry::make('clientProfile.occupation')->label('Povolání')->placeholder('—'),
                        TextEntry::make('clientProfile.weight')->label('Váha (kg)')->placeholder('—'),
                        TextEntry::make('clientProfile.height')->label('Výška (cm)')->placeholder('—'),
                        IconEntry::make('email_verified_at')->label('Ověřen email?')->boolean(),
                        TextEntry::make('created_at')->label('Registrován')->dateTime('d.m.Y H:i'),
                    ])
                ),
                Section::make('Anamnéza')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->schema([
                        TextEntry::make('clientProfile.anamnesis')
                            ->hiddenLabel()
                            ->html()
                            ->prose()
                            ->placeholder('Bez anamnézy')
                            ->columnSpanFull(),
                    ]),
                Section::make('Fakturační údaje')
                    ->icon(Heroicon::OutlinedBuildingOffice)
                    ->gridContainer()
                    ->columns(ResponsiveColumns::PAIR)
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('clientProfile.billing_name')
                            ->label('Název / jméno na faktuře')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('clientProfile.company_ico')->label('IČO')->placeholder('—'),
                        TextEntry::make('clientProfile.company_dic')->label('DIČ')->placeholder('—'),
                        TextEntry::make('clientProfile.billing_address')
                            ->label('Fakturační adresa')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
