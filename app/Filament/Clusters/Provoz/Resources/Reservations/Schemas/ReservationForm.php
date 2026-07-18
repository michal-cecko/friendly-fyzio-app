<?php

namespace App\Filament\Clusters\Provoz\Resources\Reservations\Schemas;

use App\Filament\Clusters\Provoz\Resources\Clients\ClientResource;
use App\Filament\Clusters\Provoz\Resources\Services\ServiceResource;
use App\Filament\Clusters\Provoz\Resources\TherapistProfiles\TherapistProfileResource;
use App\Filament\Support\Schemas\PresenceBanner;
use App\Filament\Support\Schemas\RecordTimestamps;
use App\Filament\Support\Schemas\ResponsiveColumns;
use App\Models\TherapistProfile;
use App\Support\Mentions\StaffMentions;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
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
        return $schema->components([
            PresenceBanner::make(),
            ...self::components(),
        ]);
    }

    /**
     * The "Upozornit zákazníka?" toggle appended to the reservation edit page.
     * A UI-only field (not a model attribute); read in the page's afterSave.
     */
    public static function notifyClientToggle(): Toggle
    {
        return Toggle::make('notify_client')
            ->label('Upozornit zákazníka?')
            ->helperText('Po uložení odešle zákazníkovi i terapeutovi e-mail o změně rezervace.')
            ->default(true);
    }

    /**
     * @param  bool  $withControlTherapy  When false the "Kontrolní terapie" toggle is left
     *                                    out so a caller (the calendar) can place it in a
     *                                    shared row with its own "Upozornit zákazníka" toggle.
     * @param  bool  $withHeaderLinks  When false the "open detail" header link actions are
     *                                 omitted (redundant when editing from the detail page).
     * @return array<int, Component>
     */
    public static function components(bool $withControlTherapy = true, bool $withHeaderLinks = true): array
    {
        return [
            // Termín + účastníci in ONE uncontained group (date/time row above the
            // participant selects), so the modal isn't over-divided. The header holds the
            // "Otevřít detail" links — Filament modals don't expose the window header
            // (next to the ✕) for custom actions, so this is the closest spot.
            Section::make()
                ->contained(false)
                ->headerActions($withHeaderLinks ? [
                    self::openRecordAction(
                        'openClient',
                        'Klient',
                        'client_id',
                        fn (string $id): string => ClientResource::getUrl('view', ['record' => $id]),
                    ),
                    self::openRecordAction(
                        'openService',
                        'Služba',
                        'service_id',
                        fn (string $id): string => ServiceResource::getUrl('view', ['record' => $id]),
                    ),
                    self::openRecordAction(
                        'openTherapist',
                        'Terapeut',
                        'therapist_id',
                        fn (string $id): string => TherapistProfileResource::getUrl('edit', ['record' => $id]),
                    ),
                ] : [])
                ->schema([
                    Grid::make(3)->schema([
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
                    Grid::make(ResponsiveColumns::DENSE)->schema([
                        Select::make('client_id')
                            ->label('Klient')
                            ->relationship('client', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            // Reassigning the client would make it a different reservation, so
                            // the client is locked once the reservation exists.
                            ->disabled(fn (string $operation): bool => $operation === 'edit')
                            ->helperText(fn (string $operation): ?string => $operation === 'edit'
                                ? 'Klienta nelze u existující rezervace změnit — vážou se na něj platby, doklady a e-mailová komunikace. Potřebujete-li rezervaci převést, zrušte ji a založte novou.'
                                : null),
                        Select::make('service_id')
                            ->label('Služba')
                            ->relationship('service', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),
                        Select::make('therapist_id')
                            ->label('Terapeut')
                            ->relationship('therapist')
                            ->getOptionLabelFromRecordUsing(fn (TherapistProfile $record): ?string => $record->user?->name)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),
                        Select::make('room_id')
                            ->label('Místnost')
                            ->relationship('room', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                    ]),
                    RecordTimestamps::entries(),
                ]),
            // Status is never hand-edited here: new reservations default to Pending, and
            // confirming / unconfirming / cancelling all go through their dedicated
            // actions so the confirmation source (who + how) is always recorded.
            Section::make('Doplňující údaje')
                ->contained(false)
                ->gridContainer()
                ->columns(ResponsiveColumns::DENSE)
                ->schema([
                    ...($withControlTherapy ? [self::controlTherapyToggle()] : []),
                    RichEditor::make('notes')
                        ->label('Poznámka')
                        ->mentions([StaffMentions::editorProvider()])
                        ->toolbarButtons([
                            ['bold', 'italic', 'link', 'textColor'],
                        ])
                        ->columnSpanFull(),
                ]),
        ];
    }

    /**
     * The "Kontrolní terapie" toggle, exposed so the calendar can place it beside its own
     * "Upozornit zákazníka" toggle (see {@see components()} `$withControlTherapy`).
     */
    public static function controlTherapyToggle(): Toggle
    {
        return Toggle::make('is_control_therapy')
            ->label('Kontrolní terapie');
    }

    /**
     * A header link that opens the selected record's detail page in a new tab, shown only
     * once a value is picked. Lets the admin jump straight to the chosen client / service
     * / therapist from the reservation form.
     *
     * @param  callable(string): string  $url  Builds the target URL from the selected id.
     */
    private static function openRecordAction(string $name, string $label, string $field, callable $url): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->color('gray')
            ->link()
            ->visible(fn (Get $get): bool => filled($get($field)))
            ->url(fn (Get $get): ?string => filled($get($field)) ? $url($get($field)) : null, shouldOpenInNewTab: true);
    }
}
