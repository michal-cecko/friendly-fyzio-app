<?php

namespace App\Filament\Clusters\Provoz\Resources\Clients\Schemas;

use App\Filament\Support\Schemas\PresenceBanner;
use App\Filament\Support\Schemas\RecordTimestampsSection;
use App\Filament\Support\Schemas\ResponsiveColumns;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                PresenceBanner::make(),
                RecordTimestampsSection::firstRow(
                    Section::make('Osobní údaje')
                        ->icon(Heroicon::OutlinedUser)
                        ->gridContainer()
                        ->columns(ResponsiveColumns::DENSE)
                        ->schema([
                            TextInput::make('name')
                                ->label('Jméno')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('email')
                                ->label('E-mail')
                                ->email()
                                ->required()
                                ->unique(User::class, ignoreRecord: true)
                                ->maxLength(255),
                            TextInput::make('phone')
                                ->label('Telefon')
                                ->tel()
                                ->maxLength(255),
                            Group::make()
                                ->relationship('clientProfile')
                                ->columnSpanFull()
                                ->gridContainer()
                                ->columns(ResponsiveColumns::DENSE)
                                ->schema([
                                    DatePicker::make('date_of_birth')
                                        ->label('Datum narození')
                                        ->native(false)
                                        ->displayFormat('d.m.Y')
                                        ->maxDate(now()),
                                    TextInput::make('address_city')
                                        ->label('Město')
                                        ->maxLength(255),
                                    TextInput::make('occupation')
                                        ->label('Povolání')
                                        ->maxLength(255),
                                    TextInput::make('weight')
                                        ->label('Váha (kg)')
                                        ->numeric()
                                        ->minValue(0)
                                        ->step(0.01),
                                    TextInput::make('height')
                                        ->label('Výška (cm)')
                                        ->numeric()
                                        ->minValue(0)
                                        ->step(0.01),
                                ]),
                        ])
                ),
                Group::make()
                    ->relationship('clientProfile')
                    ->schema([
                        Section::make('Anamnéza')
                            ->icon(Heroicon::OutlinedDocumentText)
                            ->schema([
                                RichEditor::make('anamnesis')
                                    ->hiddenLabel()
                                    ->toolbarButtons([
                                        ['bold', 'italic', 'underline', 'strike'],
                                        ['bulletList', 'orderedList'],
                                        ['undo', 'redo'],
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ]),
                Group::make()
                    ->relationship('clientProfile')
                    ->schema([
                        Section::make('Fakturační údaje')
                            ->icon(Heroicon::OutlinedBuildingOffice)
                            ->gridContainer()
                            ->columns(ResponsiveColumns::PAIR)
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                TextInput::make('billing_name')
                                    ->label('Název / jméno na faktuře')
                                    ->helperText('Pokud zůstane prázdné, použije se jméno klienta.')
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                TextInput::make('company_ico')
                                    ->label('IČO')
                                    ->maxLength(255),
                                TextInput::make('company_dic')
                                    ->label('DIČ')
                                    ->maxLength(255),
                                Textarea::make('billing_address')
                                    ->label('Fakturační adresa')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
