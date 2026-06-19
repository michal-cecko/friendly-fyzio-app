<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Enums\PageStatus;
use App\Mason\BrickRegistry;
use App\Models\Page;
use Awcodes\Mason\Mason;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use RalphJSmit\Filament\MediaLibrary\Filament\Forms\Components\MediaPicker;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                                    ->afterStateUpdated(function (Set $set, ?string $state, ?Page $record): void {
                                        if (! $record && filled($state)) {
                                            $set('slug', Str::slug($state));
                                        }
                                    }),
                                TextInput::make('slug')
                                    ->label('URL slug')
                                    ->required()
                                    ->disabled(fn (?Page $record): bool => (bool) $record?->is_system)
                                    ->dehydrated()
                                    ->unique(ignoreRecord: true),
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
                                        Select::make('status')
                                            ->label('Stav')
                                            ->options(PageStatus::class)
                                            ->default(PageStatus::Draft)
                                            ->required(),
                                        DateTimePicker::make('published_at')
                                            ->label('Datum publikování'),
                                        TextInput::make('sort_order')
                                            ->label('Pořadí')
                                            ->numeric()
                                            ->default(0),
                                        Toggle::make('is_system')
                                            ->label('Systémová stránka')
                                            ->disabled()
                                            ->dehydrated(),
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
