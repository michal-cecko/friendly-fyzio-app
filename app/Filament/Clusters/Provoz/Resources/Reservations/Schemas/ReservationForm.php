<?php

namespace App\Filament\Clusters\Provoz\Resources\Reservations\Schemas;

use App\Filament\Clusters\Provoz\Resources\Clients\ClientResource;
use App\Filament\Clusters\Provoz\Resources\Services\ServiceResource;
use App\Filament\Clusters\Provoz\Resources\Users\UserResource;
use App\Filament\Support\RecordLinks;
use App\Filament\Support\Schemas\PresenceBanner;
use App\Filament\Support\Schemas\RecordTimestamps;
use App\Filament\Support\Schemas\ResponsiveColumns;
use App\Models\StaffProfile;
use App\Models\User;
use App\Support\Clients\PlaceholderEmail;
use App\Support\Clients\ResolveCustomerAccount;
use App\Support\Mentions\StaffMentions;
use App\Support\StaffScope;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

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
     * @param  bool  $withControlTherapy  When false the "Kontrolní terapie" toggle is left
     *                                    out so a caller (the calendar) can place it in a
     *                                    shared row with its own "Upozornit zákazníka" toggle.
     * @param  bool  $withHeaderLinks  When false the "open detail" header link actions are
     *                                 omitted (redundant when editing from the detail page).
     * @param  bool  $lockClient  When true the client select is locked even on create — used
     *                            when booking from a client's own page, where the owner
     *                            record already decides who the reservation belongs to.
     * @param  bool  $contained  Whether the groups render as cards. Full pages want them;
     *                           modals are a card already, so there they stay flat.
     * @return array<int, Component>
     */
    public static function components(bool $withControlTherapy = true, bool $withHeaderLinks = true, bool $lockClient = false, bool $contained = false): array
    {
        return [
            // Everything that describes the appointment — kdy, kde, kdo, co — in ONE
            // container-query grid, so it packs into 4 columns on a full page and folds
            // to 1–2 in a modal without a second layout. The header holds the "Otevřít
            // detail" links — Filament modals don't expose the window header (next to
            // the ✕) for custom actions, so this is the closest spot.
            Section::make()
                ->contained($contained)
                ->gridContainer()
                ->columns(ResponsiveColumns::DENSE)
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
                        // therapist_id is a StaffProfile id; the profile hangs off its
                        // owning User, so resolve to the user and link there.
                        fn (string $id): ?string => self::userDetailUrl($id),
                    ),
                ] : [])
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
                    Select::make('room_id')
                        ->label('Místnost')
                        ->relationship('room', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('client_id')
                        ->label('Klient')
                        ->relationship('client', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        // Reassigning the client would make it a different reservation, so
                        // the client is locked once the reservation exists.
                        ->disabled(fn (string $operation): bool => $lockClient || $operation === 'edit')
                        ->helperText(fn (string $operation): ?string => match (true) {
                            $lockClient => 'Rezervace se založí tomuto klientovi.',
                            $operation === 'edit' => 'Klienta nelze změnit.',
                            default => null,
                        })
                        // The full reason lives in a tooltip: it is long enough that, spelled
                        // out under the field, it would set the height of the whole row.
                        ->hintIcon(
                            fn (string $operation): ?Heroicon => $operation === 'edit' && ! $lockClient ? Heroicon::OutlinedInformationCircle : null,
                            tooltip: 'Na klienta se vážou platby, doklady a e-mailová komunikace. Potřebujete-li rezervaci převést, zrušte ji a založte novou.',
                        )
                        ->createOptionForm(self::newClientForm())
                        ->createOptionUsing(fn (array $data): string => self::createClient($data))
                        ->createOptionModalHeading('Nový klient')
                        ->createOptionAction(fn (Action $action): Action => $action
                            ->label('Nový klient')
                            ->modalSubmitActionLabel('Vytvořit klienta')
                            ->modalWidth(Width::Large)),
                    Select::make('service_id')
                        ->label('Služba')
                        ->relationship('service', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live(),
                    // Staff scoped to their own work book for themselves only: the
                    // picker offers nobody else and defaults to them.
                    Select::make('therapist_id')
                        ->label('Terapeut')
                        ->relationship('therapist', modifyQueryUsing: fn (Builder $query): Builder => $query
                            ->when(StaffScope::current()->staffProfileId, fn (Builder $scoped, string $id) => $scoped->whereKey($id)))
                        ->getOptionLabelFromRecordUsing(fn (StaffProfile $record): ?string => $record->user?->name)
                        ->default(fn (): ?string => StaffScope::current()->staffProfileId)
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live(),
                    // Status is never hand-edited here: new reservations default to Pending,
                    // and confirming / unconfirming / cancelling all go through their
                    // dedicated actions so the confirmation source (who + how) is recorded.
                    ...($withControlTherapy ? [self::controlTherapyToggle()] : []),
                    RecordTimestamps::entries(),
                ]),
            Section::make('Poznámka')
                ->contained($contained)
                ->collapsible($contained)
                ->schema([
                    RichEditor::make('notes')
                        ->hiddenLabel()
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
     * @param  callable(string): ?string  $url  Builds the target URL from the selected id.
     */
    private static function openRecordAction(string $name, string $label, string $field, callable $url): Action
    {
        // A viewer who may not open the target gets no link at all, rather than
        // one that dead-ends in a 403.
        return Action::make($name)
            ->label($label)
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->color('gray')
            ->link()
            ->visible(fn (Get $get): bool => filled($get($field)) && $url($get($field)) !== null)
            ->url(fn (Get $get): ?string => filled($get($field)) ? $url($get($field)) : null, shouldOpenInNewTab: true);
    }

    /**
     * The team page of the user owning a staff profile — the detail page, since
     * editing a colleague is administrators-only.
     */
    private static function userDetailUrl(string $staffProfileId): ?string
    {
        $user = User::whereKey(StaffProfile::whereKey($staffProfileId)->value('user_id'))->first();

        return $user === null
            ? null
            : RecordLinks::detailUrl(UserResource::class, $user);
    }

    /**
     * The inline "Nový klient" form opened from the client select's "+" button.
     * E-mail is required unless "Klient nemá email" is checked (a placeholder
     * address is generated instead); phone becomes required without an e-mail.
     *
     * @return array<int, Component>
     */
    private static function newClientForm(): array
    {
        return [
            Grid::make(2)->schema([
                TextInput::make('first_name')
                    ->label('Jméno')
                    ->required()
                    ->maxLength(255),
                TextInput::make('last_name')
                    ->label('Příjmení')
                    ->required()
                    ->maxLength(255),
            ]),
            Checkbox::make('no_email')
                ->label('Klient nemá email')
                ->helperText('Vygeneruje se zástupná adresa @'.PlaceholderEmail::DOMAIN.'.')
                ->live(),
            TextInput::make('email')
                ->label('E-mail')
                ->email()
                ->maxLength(255)
                ->live(onBlur: true)
                ->hidden(fn (Get $get): bool => (bool) $get('no_email'))
                ->dehydrated(fn (Get $get): bool => ! $get('no_email'))
                ->required(fn (Get $get): bool => ! $get('no_email'))
                ->unique(User::class, 'email'),
            TextInput::make('phone')
                ->label('Telefon')
                ->tel()
                ->maxLength(255)
                ->required(fn (Get $get): bool => (bool) $get('no_email') || blank($get('email'))),
        ];
    }

    /**
     * @param  array{first_name: string, last_name: string, no_email?: bool, email?: string, phone?: ?string}  $data
     */
    private static function createClient(array $data): string
    {
        $email = ($data['no_email'] ?? false)
            ? app(PlaceholderEmail::class)->generate($data['first_name'], $data['last_name'])
            : $data['email'];

        // Canonical customer creation (role, random password, client profile). The
        // unique rule in the form makes the "existing e-mail" branch unreachable
        // except under a race, where returning the existing account is correct.
        [$user] = ResolveCustomerAccount::resolve(
            authenticated: null,
            name: trim($data['first_name'].' '.$data['last_name']),
            email: $email,
            phone: $data['phone'] ?? null,
        );

        return (string) $user->getKey();
    }
}
