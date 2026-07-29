<?php

namespace App\Filament\Clusters\Provoz\Resources\Services\Schemas;

use App\Enums\ExamType;
use App\Enums\ServiceVisibility;
use App\Filament\Clusters\Provoz\Resources\Services\Pages\EditService;
use App\Filament\Support\Actions\CopyPageContentAction;
use App\Filament\Support\Schemas\BreakBlocks;
use App\Filament\Support\Schemas\DerivedSlug;
use App\Filament\Support\Schemas\PresenceBanner;
use App\Filament\Support\Schemas\ResponsiveColumns;
use App\Mason\BrickRegistry;
use App\Models\Service;
use App\Models\Specialization;
use App\Models\StaffProfile;
use App\Support\Icon;
use App\Support\Settings;
use Awcodes\Mason\Mason;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Guava\IconPicker\Forms\Components\IconPicker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        $block = Settings::blockMinutes();

        return $schema
            ->components([
                PresenceBanner::make(),
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
                            ->afterStateUpdated(DerivedSlug::syncFrom(Service::class, 'sluzba'))
                            ->columnSpan(['default' => 1, 'lg' => 5]),
                        DerivedSlug::field('Automaticky z názvu, používá se v URL.')
                            ->columnSpan(['default' => 1, 'lg' => 3]),
                        Select::make('category_id')
                            ->label('Kategorie')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->columnSpan(['default' => 1, 'lg' => 4]),
                        IconPicker::make('icon')
                            ->label('Ikona')
                            ->sets(['lucide'])
                            ->searchable()
                            ->helperText('Volitelné. Bez ikony se použije ikona kategorie.')
                            ->columnSpan(['default' => 1, 'lg' => 4]),
                        Select::make('exam_type')
                            ->label('Typ vyšetření')
                            ->options(ExamType::class)
                            ->native(false)
                            ->live()
                            ->helperText('Jen pro fyzioterapii: vstupní (noví pacienti) vs kontrolní (stávající).')
                            ->columnSpan(['default' => 1, 'lg' => 4]),
                        TextInput::make('existing_client_months')
                            ->label('Stávající klient do (měsíce)')
                            ->numeric()
                            ->minValue(1)
                            ->suffix('měs.')
                            ->helperText('Klient je „stávající“, pokud byl naposledy max. před tolika měsíci.')
                            ->visible(fn (Get $get): bool => $get('exam_type') === ExamType::Kontrolni->value)
                            ->columnSpan(['default' => 1, 'lg' => 4]),
                        TextInput::make('invoice_title')
                            ->label('Název pro fakturaci')
                            ->maxLength(255)
                            ->helperText('Použije se na fakturách a v e-mailech místo běžného názvu.')
                            ->columnSpan(['default' => 1, 'lg' => 4]),
                        RichEditor::make('description')
                            ->label('Popis')
                            ->toolbarButtons([
                                ['bold', 'italic', 'link', 'bulletList', 'orderedList'],
                            ])
                            ->helperText('Text pro výchozí stránku služby. Služby s vlastní stránkou ho nepoužijí.')
                            ->columnSpanFull(),
                    ]),
                Grid::make()
                    ->columnSpanFull()
                    ->gridContainer()
                    ->columns(['default' => 1, '@3xl' => 2])
                    ->schema([
                        Section::make('Délka a cena')
                            ->icon(Heroicon::OutlinedBanknotes)
                            ->gridContainer()
                            ->columns(ResponsiveColumns::DENSE)
                            ->schema([
                                TextInput::make('duration_minutes')
                                    ->label('Délka')
                                    ->numeric()
                                    ->required()
                                    ->suffix('min')
                                    ->step($block)
                                    ->minValue($block)
                                    ->helperText('Násobky '.$block.' min'),
                                TextInput::make('price')
                                    ->label('Cena')
                                    ->integer()
                                    ->required()
                                    ->minValue(0)
                                    ->suffix('Kč'),
                            ]),
                        Section::make('Viditelnost a publikování')
                            ->icon(Heroicon::OutlinedEye)
                            ->gridContainer()
                            ->columns(ResponsiveColumns::PAIR)
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
                            ->gridContainer()
                            ->columns(ResponsiveColumns::PAIR)
                            ->schema([
                                TextInput::make('cancel_before_hours')
                                    ->label('Zrušit nejpozději (hodin předem)')
                                    ->integer()
                                    ->required()
                                    ->default(24)
                                    ->minValue(0),
                            ]),
                        Section::make('Místnosti a terapeuti')
                            ->icon(Heroicon::OutlinedBuildingOffice)
                            ->schema([
                                Select::make('rooms')
                                    ->label('Místnosti')
                                    ->relationship('rooms', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->searchable(),
                                Repeater::make('serviceTherapists')
                                    ->relationship()
                                    ->label('Terapeuti')
                                    ->helperText('Pauza zůstane prázdná, pokud terapeutovi u této služby stačí jeho výchozí.')
                                    ->table([
                                        TableColumn::make('Terapeut')->markAsRequired(),
                                        TableColumn::make('Pauza po termínu'),
                                    ])
                                    ->schema([
                                        Select::make('therapist_id')
                                            ->label('Terapeut')
                                            ->options(self::therapistOptions())
                                            ->searchable()
                                            ->required()
                                            ->distinct()
                                            // Drives the „Výchozí (15 min)" placeholder next to it.
                                            ->live(),
                                        BreakBlocks::override(),
                                    ])
                                    ->defaultItems(0)
                                    ->addActionLabel('Přidat terapeuta'),
                            ]),
                    ]),
                Section::make('Možné specializace')
                    ->key('specializations')
                    ->icon(Heroicon::OutlinedSparkles)
                    ->description('Sdílený číselník specializací pod touto službou. Terapeuti si je vybírají na svém profilu (seskupené podle služby), takže se nepíšou znovu pro každého. Na profilu terapeuta pak specializace odkazuje na rezervaci právě této služby.')
                    ->columnSpanFull()
                    ->headerActions([
                        self::attachSpecializationAction(),
                    ])
                    ->schema([
                        Repeater::make('specializations')
                            ->relationship()
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Název')->markAsRequired(),
                                TableColumn::make('Ikona'),
                                TableColumn::make('Popis'),
                            ])
                            ->schema([
                                TextInput::make('name')
                                    ->label('Název')
                                    ->required(),
                                IconPicker::make('icon')
                                    ->label('Ikona')
                                    ->sets(['lucide'])
                                    ->searchable()
                                    // Older rows stored bare lucide names („heart"),
                                    // which the picker rejects as unknown icons.
                                    ->formatStateUsing(fn (?string $state): ?string => Icon::name($state)),
                                Textarea::make('description')
                                    ->label('Popis')
                                    ->rows(1)
                                    ->autosize(),
                            ])
                            ->orderColumn('display_order')
                            ->reorderable()
                            ->defaultItems(0)
                            ->addActionLabel('Přidat specializaci'),
                    ]),
                Section::make('Vlastní stránka (přepíše výchozí rozvržení)')
                    ->description('Sestavte vlastní vzhled z bloků. Jakmile přidáte obsah, nahradí výchozí rozvržení této služby.')
                    ->columnSpanFull()
                    ->relationship('customPage', condition: fn (?array $state): bool => filled($state['content'] ?? null))
                    ->mutateRelationshipDataBeforeCreateUsing(fn (array $data, Service $record): array => [
                        ...$data,
                        'title' => $data['title'] ?? $record->name,
                        'slug' => $data['slug'] ?? Str::slug($record->slug.'-vlastni-stranka'),
                    ])
                    ->schema([
                        DateTimePicker::make('published_at')
                            ->label('Datum publikování')
                            ->helperText('Bez data = koncept, viditelný jen pro administrátory.'),
                        Mason::make('content')
                            ->label('Obsah')
                            ->bricks(BrickRegistry::all())
                            ->hintAction(CopyPageContentAction::make())
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Pull an existing catalogue entry under this service. The repeater below
     * only ever creates new entries, so without this the ones that belong
     * nowhere (or belong somewhere else) could only be moved from the
     * Specializace resource — the mapping is meant to be reachable from both
     * ends, since it is what makes the entry a booking link.
     */
    private static function attachSpecializationAction(): Action
    {
        return Action::make('attachSpecialization')
            ->label('Přidat existující')
            ->icon(Heroicon::OutlinedLink)
            ->visible(fn (?Service $record): bool => $record !== null)
            ->schema([
                Select::make('specialization_id')
                    ->label('Specializace')
                    ->options(fn (Service $record): array => self::attachableSpecializations($record))
                    ->searchable()
                    ->required()
                    ->helperText('Nezařazené specializace i ty, které dnes patří pod jinou službu.'),
            ])
            ->action(function (array $data, Service $record): void {
                Specialization::query()
                    ->whereKey($data['specialization_id'])
                    ->update(['service_id' => $record->getKey()]);

                Notification::make()
                    ->success()
                    ->title('Specializace přiřazena')
                    ->send();
            })
            // The row was written straight to the database, so the open form has
            // to be told to re-read the relationship it does not know changed.
            ->after(fn (EditService $livewire) => $livewire->refreshFormData(['specializations']));
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function attachableSpecializations(Service $record): array
    {
        return Specialization::query()
            ->with('service')
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('service_id')
                ->orWhere('service_id', '!=', $record->getKey()))
            ->orderBy('name')
            ->get(['id', 'name', 'service_id'])
            ->groupBy(fn (Specialization $specialization): string => $specialization->service?->name ?? 'Nezařazené')
            ->sortBy(fn ($group, string $label): int => $label === 'Nezařazené' ? 0 : 1)
            ->map(fn ($group) => $group->pluck('name', 'id'))
            ->toArray();
    }

    /**
     * Every staff profile, by display name. Deliberately unfiltered: who may
     * actually be booked is decided by {@see StaffProfile::scopeBookable()} at
     * offering time, so a lecturer or an assistant can still be linked here
     * without becoming bookable.
     *
     * @return array<string, string>
     */
    private static function therapistOptions(): array
    {
        return StaffProfile::query()
            ->with('user')
            ->get(['id', 'user_id'])
            ->sortBy(fn (StaffProfile $profile): string => $profile->user?->name ?? '')
            ->mapWithKeys(fn (StaffProfile $profile): array => [
                $profile->getKey() => $profile->user?->full_name ?? '—',
            ])
            ->all();
    }
}
