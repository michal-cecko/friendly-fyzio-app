<?php

namespace App\Filament\Clusters\Provoz\Resources\Users\Schemas;

use App\Filament\Support\Schemas\BreakBlocks;
use App\Mason\Support\Fields;
use App\Models\Service;
use App\Models\Specialization;
use App\Models\StaffProfile;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use RalphJSmit\Filament\MediaLibrary\Filament\Forms\Components\MediaPicker;

/**
 * The public-profile ({@see StaffProfile}) fields, edited inline on
 * the User form via a `Group::make()->relationship('staffProfile')`. The member's
 * name and academic titles live on the User's "Účet" tab, so they are not
 * repeated here; the slug is read-only and auto-derived from the name.
 */
class StaffProfileSection
{
    /**
     * @return array<int, Component>
     */
    public static function components(): array
    {
        return [
            Tabs::make('Veřejný profil')
                ->columnSpanFull()
                ->schema([
                    Tab::make('Základní údaje')
                        ->icon(Heroicon::OutlinedIdentification)
                        ->schema([
                            Grid::make(['default' => 1, 'lg' => 2])
                                ->schema([
                                    MediaPicker::make('photo')
                                        ->label('Fotografie')
                                        ->acceptedFileTypes(['image/*']),
                                    Group::make([
                                        TextInput::make('title')
                                            ->label('Pozice')
                                            ->maxLength(255)
                                            ->placeholder('Fyzioterapeutka, zakladatelka')
                                            ->helperText('Profese uvedená pod jménem v týmu i v hlavičce profilu.'),
                                        TextInput::make('badge')
                                            ->label('Odznak')
                                            ->maxLength(255)
                                            ->placeholder('Zakladatelka & hlavní terapeutka')
                                            ->helperText('Krátký zvýrazněný štítek nad jménem na kartě v týmu. Nechte prázdné, pokud jej člen nemá mít.'),
                                        Grid::make(2)
                                            ->schema([
                                                Toggle::make('is_collaborator')
                                                    ->label('Spolupracující terapeut')
                                                    ->helperText('Externí spolupracovník, nikoli kmenový člen týmu.'),
                                                TextInput::make('display_order')
                                                    ->label('Pořadí')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->helperText('Nižší číslo = dříve v seznamu týmu.'),
                                            ]),
                                        DateTimePicker::make('published_at')
                                            ->label('Publikováno')
                                            ->helperText('Bez data (nebo budoucí datum) = koncept, profil není veřejně přístupný a v týmu není proklikávací.'),
                                        TextInput::make('slug')
                                            ->label('URL název')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->helperText('Součást URL profilu. Odvozuje se automaticky ze jména člena týmu, nelze upravovat ručně.'),
                                    ]),
                                ]),
                        ]),
                    Tab::make('Provoz')
                        ->icon(Heroicon::OutlinedClock)
                        ->schema([
                            Grid::make(['default' => 1, 'lg' => 2])
                                ->schema([
                                    BreakBlocks::field()
                                        // Drives the „Výchozí (15 min)" placeholder in the rows below.
                                        ->live(),
                                ]),
                            Repeater::make('serviceTherapists')
                                ->relationship()
                                ->label('Služby')
                                ->helperText('Které služby tento terapeut provádí. Totéž lze nastavit i u jednotlivých služeb — je to jedna a tatáž tabulka.')
                                ->table([
                                    TableColumn::make('Služba')->markAsRequired(),
                                    TableColumn::make('Pauza po termínu'),
                                ])
                                ->schema([
                                    Select::make('service_id')
                                        ->label('Služba')
                                        ->options(self::serviceOptions(...))
                                        ->searchable()
                                        ->required()
                                        ->distinct(),
                                    BreakBlocks::inheriting(defaultBlocks: fn (Get $get): ?int => self::defaultBreakBlocks($get)),
                                ])
                                ->defaultItems(0)
                                ->addActionLabel('Přidat službu'),
                        ]),
                    Tab::make('O mně')
                        ->icon(Heroicon::OutlinedUser)
                        ->schema([
                            Fields::richText('bio', 'Medailonek')
                                ->helperText('Delší text pod jménem na profilu člena týmu. Krátce představte sebe a svůj přístup.'),
                        ]),
                    Tab::make('Specializace')
                        ->icon(Heroicon::OutlinedSparkles)
                        ->schema([
                            Repeater::make('specializations')
                                ->relationship()
                                ->label('Specializace')
                                ->helperText('Štítky v hlavičce profilu i v týmu a karty v sekci „V čem vám mohu pomoci“. Vyberte ze sdíleného číselníku (definuje se u služeb, zde seskupeno podle služby).')
                                ->table([
                                    TableColumn::make('Specializace')->markAsRequired(),
                                ])
                                ->schema([
                                    Select::make('specialization_id')
                                        ->label('Specializace')
                                        ->options(fn (): array => Specialization::query()
                                            ->with('service')
                                            ->orderBy('display_order')
                                            ->get()
                                            ->groupBy(fn (Specialization $specialization): string => $specialization->service?->name ?? 'Ostatní')
                                            ->map(fn ($group) => $group->pluck('name', 'id'))
                                            ->toArray())
                                        ->searchable()
                                        ->required()
                                        ->distinct(),
                                ])
                                ->orderColumn('display_order')
                                ->reorderable()
                                ->defaultItems(0)
                                ->addActionLabel('Přidat specializaci'),
                        ]),
                    Tab::make('Vzdělání')
                        ->icon(Heroicon::OutlinedAcademicCap)
                        ->schema([
                            Repeater::make('education')
                                ->label('Vzdělání')
                                ->table([
                                    TableColumn::make('Titul / obor')->markAsRequired(),
                                    TableColumn::make('Instituce'),
                                    TableColumn::make('Období'),
                                ])
                                ->schema([
                                    TextInput::make('degree')
                                        ->label('Titul / obor')
                                        ->required(),
                                    TextInput::make('institution')
                                        ->label('Instituce'),
                                    TextInput::make('period')
                                        ->label('Období')
                                        ->placeholder('2010 – 2012'),
                                ])
                                ->reorderable()
                                ->defaultItems(0)
                                ->addActionLabel('Přidat vzdělání'),
                        ]),
                    Tab::make('Certifikace a kurzy')
                        ->icon(Heroicon::OutlinedDocumentCheck)
                        ->schema([
                            Repeater::make('certifications')
                                ->label('Certifikace a kurzy')
                                ->table([
                                    TableColumn::make('Název')->markAsRequired(),
                                    TableColumn::make('Instituce'),
                                    TableColumn::make('Rok'),
                                ])
                                ->schema([
                                    TextInput::make('name')
                                        ->label('Název')
                                        ->required(),
                                    TextInput::make('institution')
                                        ->label('Instituce'),
                                    TextInput::make('year')
                                        ->label('Rok')
                                        ->placeholder('2023'),
                                ])
                                ->reorderable()
                                ->defaultItems(0)
                                ->addActionLabel('Přidat certifikaci'),
                        ]),
                ]),
        ];
    }

    /**
     * Every service, grouped by category — the same catalogue the service form
     * offers in reverse. The assignment is one row of `service_therapists`
     * either way, so editing it from both sides cannot produce a disagreement.
     *
     * @return array<string, array<string, string>>
     */
    private static function serviceOptions(): array
    {
        return Service::query()
            ->with('category')
            ->orderBy('name')
            ->get(['id', 'name', 'category_id'])
            ->groupBy(fn (Service $service): string => $service->category?->name ?? 'Ostatní')
            ->map(fn ($group) => $group->pluck('name', 'id'))
            ->toArray();
    }

    /**
     * The member's own default, read from the form state rather than the record
     * so the rows follow an unsaved edit of the field above them.
     */
    private static function defaultBreakBlocks(Get $get): ?int
    {
        $blocks = $get('../../break_blocks');

        return blank($blocks) ? null : (int) $blocks;
    }
}
