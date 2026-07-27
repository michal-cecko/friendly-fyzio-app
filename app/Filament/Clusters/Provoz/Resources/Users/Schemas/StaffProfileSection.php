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
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
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
                                    BreakBlocks::field(),
                                    TextEntry::make('serviceBreaks')
                                        ->label('Pauzy podle služby')
                                        ->state(self::serviceBreakSummary(...))
                                        ->listWithLineBreaks()
                                        ->bulleted()
                                        ->helperText('Nastavuje se u jednotlivých služeb. Bez vlastní hodnoty platí výchozí pauza vlevo.')
                                        ->visible(fn (?StaffProfile $record): bool => filled($record) && $record->services()->exists()),
                                ]),
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
     * What this member actually rests after each of their services, and whether
     * that came from the service or from their default above. Read-only on
     * purpose — the override belongs to the service assignment, so there is one
     * place it is edited rather than two that can disagree.
     *
     * @return array<int, string>
     */
    private static function serviceBreakSummary(?StaffProfile $record): array
    {
        if ($record === null) {
            return [];
        }

        return $record->services()
            ->orderBy('name')
            ->get(['services.id', 'services.name'])
            ->map(function (Service $service) use ($record): string {
                $override = $service->pivot->break_blocks;
                $blocks = $override ?? $record->break_blocks;

                return $service->name.' — '.BreakBlocks::minutesLabel($blocks)
                    .($override === null ? ' (výchozí)' : ' (vlastní)');
            })
            ->all();
    }
}
