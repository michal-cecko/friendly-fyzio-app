<?php

namespace App\Filament\Resources\Services\Schemas;

use App\Enums\ServiceVisibility;
use App\Models\TherapistProfile;
use App\Support\Settings;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        $block = Settings::blockMinutes();

        return $schema
            ->components([
                Section::make('Základní údaje')
                    ->icon(Heroicon::OutlinedSparkles)
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'lg' => 12])
                    ->schema([
                        TextInput::make('name')
                            ->label('Název')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state ?? '')))
                            ->columnSpan(['default' => 1, 'lg' => 5]),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Automaticky z názvu, používá se v URL.')
                            ->columnSpan(['default' => 1, 'lg' => 3]),
                        Select::make('category_id')
                            ->label('Kategorie')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->columnSpan(['default' => 1, 'lg' => 4]),
                    ]),
                Section::make('Délka a cena')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'sm' => 3])
                    ->schema([
                        TextInput::make('duration_minutes')
                            ->label('Délka')
                            ->numeric()
                            ->required()
                            ->suffix('min')
                            ->step($block)
                            ->minValue($block)
                            ->helperText('Násobky '.$block.' min'),
                        TextInput::make('break_minutes')
                            ->label('Pauza')
                            ->numeric()
                            ->default(0)
                            ->suffix('min')
                            ->step($block)
                            ->minValue(0),
                        TextInput::make('price')
                            ->label('Cena')
                            ->integer()
                            ->required()
                            ->minValue(0)
                            ->suffix('Kč'),
                    ]),
                Section::make('Viditelnost a publikování')
                    ->icon(Heroicon::OutlinedEye)
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'sm' => 2])
                    ->schema([
                        Select::make('visibility')
                            ->label('Viditelnost')
                            ->options(ServiceVisibility::class)
                            ->default(ServiceVisibility::Public)
                            ->required()
                            ->native(false),
                        DateTimePicker::make('published_at')
                            ->label('Publikováno')
                            ->native(false)
                            ->helperText('Datum, od kterého je služba viditelná na veřejném webu. Bez data není zveřejněna.')
                            ->suffixAction(
                                Action::make('clearPublishedAt')
                                    ->icon(Heroicon::XMark)
                                    ->label('Vymazat')
                                    ->visible(fn (?string $state): bool => filled($state))
                                    ->action(fn (Set $set) => $set('published_at', null)),
                            ),
                    ]),
                Section::make('Storno podmínky')
                    ->icon(Heroicon::OutlinedClock)
                    ->relationship('cancellationRule')
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'sm' => 2])
                    ->schema([
                        TextInput::make('cancel_before_hours')
                            ->label('Zrušit nejpozději (hodin předem)')
                            ->integer()
                            ->required()
                            ->default(24)
                            ->minValue(0),
                        TextInput::make('auto_cancel_after_days')
                            ->label('Automaticky zrušit po (dnech)')
                            ->integer()
                            ->minValue(0),
                    ]),
                Section::make('Místnosti a terapeuti')
                    ->icon(Heroicon::OutlinedBuildingOffice)
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema([
                        Select::make('rooms')
                            ->label('Místnosti')
                            ->relationship('rooms', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable(),
                        Select::make('therapists')
                            ->label('Terapeuti')
                            ->relationship('therapists')
                            ->getOptionLabelFromRecordUsing(fn (TherapistProfile $record): ?string => $record->user?->name)
                            ->multiple()
                            ->preload(),
                    ]),
            ]);
    }
}
