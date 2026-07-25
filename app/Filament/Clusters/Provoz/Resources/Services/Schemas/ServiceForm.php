<?php

namespace App\Filament\Clusters\Provoz\Resources\Services\Schemas;

use App\Enums\ExamType;
use App\Enums\ServiceVisibility;
use App\Filament\Support\Schemas\DerivedSlug;
use App\Filament\Support\Schemas\PresenceBanner;
use App\Filament\Support\Schemas\ResponsiveColumns;
use App\Mason\BrickRegistry;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Support\Settings;
use Awcodes\Mason\Mason;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                            ->gridContainer()
                            ->columns(ResponsiveColumns::PAIR)
                            ->schema([
                                Select::make('rooms')
                                    ->label('Místnosti')
                                    ->relationship('rooms', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->searchable(),
                                Select::make('therapists')
                                    ->label('Terapeuti')
                                    // Limit the option query to the columns we need: Filament
                                    // adds DISTINCT for multiple relationship selects, and
                                    // Postgres cannot DISTINCT staff_profiles' json columns.
                                    ->relationship(
                                        'therapists',
                                        modifyQueryUsing: fn (Builder $query): Builder => $query->select(['staff_profiles.id', 'staff_profiles.user_id']),
                                    )
                                    ->getOptionLabelFromRecordUsing(fn (StaffProfile $record): ?string => $record->user?->name)
                                    ->multiple()
                                    ->preload(),
                            ]),
                    ]),
                Section::make('Možné specializace')
                    ->icon(Heroicon::OutlinedSparkles)
                    ->description('Sdílený číselník specializací pod touto službou. Terapeuti si je vybírají na svém profilu (seskupené podle služby), takže se nepíšou znovu pro každého.')
                    ->columnSpanFull()
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
                                    ->searchable(),
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
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
