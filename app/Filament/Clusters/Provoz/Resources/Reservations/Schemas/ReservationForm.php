<?php

namespace App\Filament\Clusters\Provoz\Resources\Reservations\Schemas;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Filament\Clusters\Provoz\Resources\Clients\ClientResource;
use App\Filament\Clusters\Provoz\Resources\Services\ServiceResource;
use App\Filament\Clusters\System\Resources\TherapistProfiles\TherapistProfileResource;
use App\Filament\Support\Schemas\PresenceBanner;
use App\Filament\Support\Schemas\RecordTimestampsSection;
use App\Filament\Support\Schemas\ResponsiveColumns;
use App\Models\TherapistProfile;
use Filament\Actions\Action;
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
use Filament\Support\Icons\Heroicon;

class ReservationForm
{
    public static function configure(Schema $schema): Schema
    {
        [$termin, $ucastnici, $stav] = self::components();

        return $schema->components([
            PresenceBanner::make(),
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
                        ->required()
                        ->live()
                        ->hintAction(self::openRecordAction(
                            'openClient',
                            'client_id',
                            fn (string $id): string => ClientResource::getUrl('view', ['record' => $id]),
                        )),
                    Select::make('service_id')
                        ->label('Služba')
                        ->relationship('service', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        ->hintAction(self::openRecordAction(
                            'openService',
                            'service_id',
                            fn (string $id): string => ServiceResource::getUrl('view', ['record' => $id]),
                        )),
                    Select::make('therapist_id')
                        ->label('Terapeut')
                        ->relationship('therapist')
                        ->getOptionLabelFromRecordUsing(fn (TherapistProfile $record): ?string => $record->user?->name)
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        ->hintAction(self::openRecordAction(
                            'openTherapist',
                            'therapist_id',
                            fn (string $id): string => TherapistProfileResource::getUrl('edit', ['record' => $id]),
                        )),
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
                ]),
        ];
    }

    /**
     * A hint-action link (top-right of a Select) that opens the selected record's detail
     * page in a new tab, shown only once a value is picked. Lets the admin jump straight
     * to the chosen client / service / therapist from the reservation form.
     *
     * @param  callable(string): string  $url  Builds the target URL from the selected id.
     */
    private static function openRecordAction(string $name, string $field, callable $url): Action
    {
        return Action::make($name)
            ->label('Otevřít detail')
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->color('gray')
            ->visible(fn (Get $get): bool => filled($get($field)))
            ->url(fn (Get $get): ?string => filled($get($field)) ? $url($get($field)) : null, shouldOpenInNewTab: true);
    }
}
