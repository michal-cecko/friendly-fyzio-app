<?php

namespace App\Filament\Clusters\Kurzy\Resources\Courses\Schemas;

use App\Filament\Support\Schemas\DerivedSlug;
use App\Filament\Support\Schemas\PresenceBanner;
use App\Models\Course;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use RalphJSmit\Filament\MediaLibrary\Filament\Forms\Components\MediaPicker;

class CourseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                PresenceBanner::make(),
                Section::make()
                    ->gridContainer()
                    ->columns(['default' => 1, '@2xl' => 3])
                    ->schema([
                        MediaPicker::make('featured_image')
                            ->label('Fotka')
                            ->acceptedFileTypes(['image/*'])
                            ->helperText('Zobrazuje se na kartě v archivu kurzů a v hlavičce detailu.')
                            ->columnSpan(['default' => 1, '@2xl' => 1]),
                        Group::make()
                            ->columns(2)
                            ->columnSpan(['default' => 1, '@2xl' => 2])
                            ->schema([
                                TextInput::make('name')
                                    ->label('Název')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(DerivedSlug::syncFrom(Course::class, 'kurz'))
                                    ->helperText('Název kurzu tak, jak se zobrazí návštěvníkům webu.')
                                    ->columnSpanFull(),
                                DerivedSlug::field('Adresa kurzu na webu. Doplní se sama z názvu.')
                                    ->columnSpanFull(),
                                Select::make('category_id')
                                    ->label('Kategorie')
                                    ->relationship('category', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->helperText('Do jaké kategorie kurz patří ve výpisu na webu.')
                                    ->columnSpan(1),
                                Select::make('instructor_id')
                                    ->label('Lektor')
                                    ->relationship('instructor', 'name', fn (Builder $query): Builder => $query->lecturers())
                                    // The lecturers() scope also filters the label lookup, so a course
                                    // assigned to a non-lecturer would show the raw UUID. Resolve the
                                    // selected value's label unscoped so the name always renders.
                                    ->getOptionLabelUsing(fn ($value): ?string => User::find($value)?->name)
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->required()
                                    ->helperText('Kdo kurz vede. Nabízejí se uživatelé s oprávněním lektor.')
                                    ->columnSpan(1),
                                RichEditor::make('description')
                                    ->label('Popis')
                                    ->toolbarButtons([
                                        ['bold', 'italic', 'link', 'bulletList', 'orderedList'],
                                    ])
                                    ->helperText('Krátké představení kurzu pro kartu a detail na webu.')
                                    ->columnSpanFull(),
                                TextInput::make('early_cancel_hours')
                                    ->label('Včasné zrušení (hodin předem)')
                                    ->integer()
                                    ->minValue(0)
                                    ->default(24)
                                    ->suffix('h')
                                    ->helperText('Do kolika hodin před lekcí lze zrušit účast bez ztráty nároku na náhradu. Kolik náhrad se smí vybrat, se nastavuje u jednotlivých sérií.')
                                    ->columnSpan(1),
                                TextInput::make('drop_in_price')
                                    ->label('Cena jednorázového vstupu')
                                    ->integer()
                                    ->minValue(0)
                                    ->suffix('Kč')
                                    ->helperText('Za kolik se prodá jedno volné místo na lekci tohoto kurzu. Prázdné = lekce se jednotlivě neprodávají.')
                                    ->columnSpan(1),
                                DateTimePicker::make('published_at')
                                    ->label('Publikováno')
                                    ->native(false)
                                    ->helperText('Bez data (nebo budoucí datum) = skryté, viditelné jen pro administrátory.')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
