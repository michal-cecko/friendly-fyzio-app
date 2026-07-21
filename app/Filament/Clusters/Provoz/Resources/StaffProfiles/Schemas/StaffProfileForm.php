<?php

namespace App\Filament\Clusters\Provoz\Resources\StaffProfiles\Schemas;

use App\Filament\Support\Schemas\PresenceBanner;
use App\Filament\Support\Schemas\RecordTimestamps;
use App\Mason\Support\Fields;
use App\Models\StaffProfile;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Guava\IconPicker\Forms\Components\IconPicker;
use Illuminate\Database\Eloquent\Builder;
use RalphJSmit\Filament\MediaLibrary\Filament\Forms\Components\MediaPicker;

class StaffProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            PresenceBanner::make(),
            ...self::components(withUser: true),
        ]);
    }

    /**
     * @return array<int, Component>
     */
    public static function components(bool $withUser = true): array
    {
        return [
            Section::make('Základní údaje')
                ->columns(2)
                ->schema(array_values(array_filter([
                    $withUser
                        ? Select::make('user_id')
                            ->label('Terapeut (uživatel)')
                            ->relationship('user', 'name', fn (Builder $query): Builder => $query->therapists())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpanFull()
                        : null,
                    Group::make()
                        ->relationship('user')
                        ->visible(fn (?StaffProfile $record): bool => $record?->user !== null)
                        ->columnSpanFull()
                        ->columns(2)
                        ->schema([
                            TextInput::make('title_before')
                                ->label('Titul před jménem')
                                ->placeholder('Bc.')
                                ->maxLength(255),
                            TextInput::make('title_after')
                                ->label('Titul za jménem')
                                ->placeholder('DiS.')
                                ->maxLength(255),
                        ]),
                    TextInput::make('title')
                        ->label('Pozice')
                        ->maxLength(255)
                        ->placeholder('Fyzioterapeutka, zakladatelka'),
                    TextInput::make('badge')
                        ->label('Odznak')
                        ->maxLength(255)
                        ->placeholder('Zakladatelka & hlavní terapeutka'),
                    TextInput::make('slug')
                        ->label('Slug')
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->helperText('Součást URL profilu. Necháte-li prázdné, vygeneruje se ze jména.')
                        ->columnSpanFull(),
                    MediaPicker::make('photo')
                        ->label('Fotografie')
                        ->acceptedFileTypes(['image/*'])
                        ->columnSpanFull(),
                    Toggle::make('is_collaborator')
                        ->label('Spolupracující terapeut'),
                    TextInput::make('display_order')
                        ->label('Pořadí')
                        ->numeric()
                        ->default(0)
                        ->helperText('Nižší číslo = dříve v seznamu týmu.'),
                    DateTimePicker::make('published_at')
                        ->label('Publikováno')
                        ->helperText('Bez data (nebo budoucí datum) = koncept, profil není veřejně přístupný a v týmu není proklikávací.')
                        ->columnSpanFull(),
                    RecordTimestamps::entries(),
                ]))),
            Section::make('O mně')
                ->schema([
                    Fields::richText('bio', 'Medailonek'),
                ]),
            Section::make('Specializace')
                ->description('Štítky v hlavičce profilu i v týmu a karty v sekci „V čem vám mohu pomoci“.')
                ->schema([
                    Repeater::make('specializations')
                        ->relationship()
                        ->label('Specializace')
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
                                ->rows(2)
                                ->columnSpanFull(),
                        ])
                        ->columns(2)
                        ->orderColumn('display_order')
                        ->reorderable()
                        ->collapsible()
                        ->defaultItems(0)
                        ->addActionLabel('Přidat specializaci')
                        ->itemLabel(fn (array $state): ?string => $state['name'] ?? null),
                ]),
            Section::make('Vzdělání')
                ->schema([
                    Repeater::make('education')
                        ->label('Vzdělání')
                        ->schema([
                            TextInput::make('degree')
                                ->label('Titul / obor')
                                ->required()
                                ->columnSpanFull(),
                            TextInput::make('institution')
                                ->label('Instituce'),
                            TextInput::make('period')
                                ->label('Období')
                                ->placeholder('2010 – 2012'),
                        ])
                        ->columns(2)
                        ->reorderable()
                        ->collapsible()
                        ->collapsed()
                        ->defaultItems(0)
                        ->addActionLabel('Přidat vzdělání')
                        ->itemLabel(fn (array $state): ?string => $state['degree'] ?? null),
                ]),
            Section::make('Certifikace a kurzy')
                ->schema([
                    Repeater::make('certifications')
                        ->label('Certifikace a kurzy')
                        ->schema([
                            TextInput::make('name')
                                ->label('Název')
                                ->required()
                                ->columnSpanFull(),
                            TextInput::make('institution')
                                ->label('Instituce'),
                            TextInput::make('year')
                                ->label('Rok')
                                ->placeholder('2023'),
                        ])
                        ->columns(2)
                        ->reorderable()
                        ->collapsible()
                        ->collapsed()
                        ->defaultItems(0)
                        ->addActionLabel('Přidat certifikaci')
                        ->itemLabel(fn (array $state): ?string => $state['name'] ?? null),
                ]),
        ];
    }
}
