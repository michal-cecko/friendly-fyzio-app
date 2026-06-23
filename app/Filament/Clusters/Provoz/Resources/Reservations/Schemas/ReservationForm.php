<?php

namespace App\Filament\Clusters\Provoz\Resources\Reservations\Schemas;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Filament\Support\Schemas\RecordTimestampsSection;
use App\Filament\Support\Schemas\ResponsiveColumns;
use App\Models\TherapistProfile;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ReservationForm
{
    public static function configure(Schema $schema): Schema
    {
        [$termin, $ucastnici, $stav] = self::components();

        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 3])
                ->columnSpanFull()
                ->schema([
                    $termin->columnSpan(['default' => 'full', 'lg' => 2]),
                    RecordTimestampsSection::make()->collapsed(false),
                ]),
            Grid::make(['default' => 1, 'lg' => 2])
                ->columnSpanFull()
                ->schema([
                    $ucastnici->columnSpan(['default' => 'full', 'lg' => 1]),
                    $stav->columnSpan(['default' => 'full', 'lg' => 1]),
                ]),
        ]);
    }

    /**
     * @return array<int, Component>
     */
    public static function components(): array
    {
        return [
            Section::make('Termín')
                ->columns(3)
                ->schema([
                    DatePicker::make('reservation_date')
                        ->label('Datum')
                        ->required()
                        ->native(false),
                    TimePicker::make('start_time')
                        ->label('Od')
                        ->seconds(false)
                        ->required(),
                    TimePicker::make('end_time')
                        ->label('Do')
                        ->seconds(false)
                        ->required()
                        ->after('start_time'),
                ]),
            Section::make('Účastníci')
                ->gridContainer()
                ->columns(ResponsiveColumns::DENSE)
                ->schema([
                    Select::make('client_id')
                        ->label('Klient')
                        ->relationship('client', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('service_id')
                        ->label('Služba')
                        ->relationship('service', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('therapist_id')
                        ->label('Terapeut')
                        ->relationship('therapist')
                        ->getOptionLabelFromRecordUsing(fn (TherapistProfile $record): ?string => $record->user?->name)
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('room_id')
                        ->label('Místnost')
                        ->relationship('room', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                ]),
            Section::make('Stav')
                ->gridContainer()
                ->columns(ResponsiveColumns::DENSE)
                ->schema([
                    ToggleButtons::make('status')
                        ->label('Stav')
                        ->options(ReservationStatus::class)
                        ->default(ReservationStatus::Pending)
                        ->required()
                        ->live()
                        ->inline(),
                    ToggleButtons::make('payment_status')
                        ->label('Platba')
                        ->options(PaymentStatus::class)
                        ->default(PaymentStatus::Unpaid)
                        ->required()
                        ->inline(),
                    Textarea::make('cancellation_reason')
                        ->label('Důvod zrušení')
                        ->rows(2)
                        ->visible(fn (Get $get): bool => in_array($get('status'), [ReservationStatus::Cancelled, ReservationStatus::Cancelled->value], true))
                        ->columnSpanFull(),
                    Toggle::make('is_control_therapy')
                        ->label('Kontrolní terapie'),
                    Textarea::make('notes')
                        ->label('Poznámka')
                        ->rows(3)
                        ->columnSpanFull(),
                    Toggle::make('notify_client')
                        ->label('Informovat klienta e-mailem')
                        ->helperText('Po uložení odešle klientovi potvrzovací e-mail.')
                        ->default(false)
                        ->columnSpanFull(),
                ]),
        ];
    }
}
