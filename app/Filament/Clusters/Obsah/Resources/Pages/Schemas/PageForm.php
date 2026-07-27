<?php

namespace App\Filament\Clusters\Obsah\Resources\Pages\Schemas;

use App\Filament\Support\Schemas\DerivedSlug;
use App\Filament\Support\Schemas\PresenceBanner;
use App\Mason\BrickRegistry;
use App\Models\Page;
use App\Models\ServiceCategory;
use Awcodes\Mason\Mason;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use RalphJSmit\Filament\MediaLibrary\Filament\Forms\Components\MediaPicker;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                PresenceBanner::make(),
                Grid::make(3)
                    ->columnSpanFull()
                    ->schema([
                        Section::make('Obsah')
                            ->columnSpan(2)
                            ->schema([
                                TextInput::make('title')
                                    ->label('Název')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(DerivedSlug::syncFrom(Page::class, 'stranka')),
                                DerivedSlug::field('Adresa stránky na webu. Doplní se sama z názvu.')
                                    ->label('URL název'),
                                Mason::make('content')
                                    ->label('Obsah stránky')
                                    ->bricks(BrickRegistry::all())
                                    ->columnSpanFull(),
                            ]),
                        Grid::make(1)
                            ->columnSpan(1)
                            ->schema([
                                Section::make('Nastavení')
                                    ->schema([
                                        DateTimePicker::make('published_at')
                                            ->label('Datum publikování')
                                            ->helperText('Bez data (nebo budoucí datum) = koncept, viditelný jen pro administrátory.'),
                                        TextInput::make('sort_order')
                                            ->label('Pořadí')
                                            ->numeric()
                                            ->default(0),
                                        Toggle::make('is_system')
                                            ->label('Systémová stránka')
                                            ->disabled()
                                            ->dehydrated(),
                                        MorphToSelect::make('pageable')
                                            ->label('Veřejná stránka pro (volitelné)')
                                            ->types([
                                                MorphToSelect\Type::make(ServiceCategory::class)
                                                    ->titleAttribute('name'),
                                            ])
                                            ->searchable(),
                                    ]),
                                Section::make('SEO')
                                    ->schema([
                                        TextInput::make('meta_title')
                                            ->label('Meta titulek'),
                                        Textarea::make('meta_description')
                                            ->label('Meta popis')
                                            ->rows(3),
                                        MediaPicker::make('featured_image')
                                            ->label('Hlavní obrázek (OG)')
                                            ->acceptedFileTypes(['image/*']),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
