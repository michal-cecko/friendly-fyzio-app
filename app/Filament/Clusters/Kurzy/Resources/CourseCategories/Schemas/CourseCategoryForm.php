<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseCategories\Schemas;

use App\Filament\Support\Schemas\DerivedSlug;
use App\Filament\Support\Schemas\PresenceBanner;
use App\Models\CourseCategory;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class CourseCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                PresenceBanner::make(),
                Section::make('Základné údaje')
                    ->icon(Heroicon::OutlinedTag)
                    ->gridContainer()
                    ->columns(['default' => 1, '@2xl' => 2])
                    ->schema([
                        TextInput::make('name')
                            ->label('Název')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(DerivedSlug::syncFrom(CourseCategory::class))
                            ->helperText('Název kategorie tak, jak se zobrazí návštěvníkům.')
                            ->columnSpanFull(),
                        DerivedSlug::field('Strojové označení kategorie. Doplní se samo z názvu.')
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('Popis')
                            ->rows(4)
                            ->helperText('Krátké představení kategorie pro výpis kurzů.')
                            ->columnSpanFull(),
                        TextInput::make('display_order')
                            ->label('Pořadí')
                            ->integer()
                            ->minValue(0)
                            ->default(0)
                            ->helperText('Nižší číslo = dřív ve výpisu.')
                            ->columnSpan(1),
                        DateTimePicker::make('published_at')
                            ->label('Publikováno')
                            ->native(false)
                            ->helperText('Bez data (nebo budoucí datum) = skryté, viditelné jen pro administrátory.')
                            ->columnSpan(1),
                    ]),
            ]);
    }
}
