<?php

namespace App\Filament\Clusters\Provoz\Resources\ServiceCategories\Schemas;

use App\Enums\ServiceType;
use App\Filament\Support\Actions\CopyPageContentAction;
use App\Filament\Support\Schemas\DerivedSlug;
use App\Filament\Support\Schemas\PresenceBanner;
use App\Filament\Support\Schemas\RecordTimestamps;
use App\Filament\Support\Schemas\ResponsiveColumns;
use App\Mason\BrickRegistry;
use App\Mason\Support\Fields;
use App\Models\ServiceCategory;
use Awcodes\Mason\Mason;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Guava\IconPicker\Forms\Components\IconPicker;
use Illuminate\Support\Str;
use RalphJSmit\Filament\MediaLibrary\Filament\Forms\Components\MediaPicker;

class ServiceCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                PresenceBanner::make(),
                Section::make('Základní údaje')
                    ->columnSpanFull()
                    ->gridContainer()
                    ->columns(ResponsiveColumns::DENSE)
                    ->schema([
                        TextInput::make('name')
                            ->label('Název')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(DerivedSlug::syncFrom(ServiceCategory::class)),
                        DerivedSlug::field('Adresa stránky kategorie na webu. Doplní se sama z názvu.'),
                        ToggleButtons::make('type')
                            ->label('Typ')
                            ->options(ServiceType::class)
                            ->inline()
                            ->columnSpanFull(),
                        IconPicker::make('icon')
                            ->label('Ikona')
                            ->sets(['lucide'])
                            ->searchable()
                            ->helperText('Zobrazí se na kartě kategorie v rezervačním průvodci.')
                            ->columnSpanFull(),
                        DateTimePicker::make('published_at')
                            ->label('Publikováno')
                            ->helperText('Bez data (nebo budoucí datum) = skryté, viditelné jen pro administrátory.')
                            ->columnSpanFull(),
                        RecordTimestamps::entries(),
                    ]),
                Section::make('Veřejná stránka (výchozí)')
                    ->description('Obsah výchozího rozvržení kategorie. Použije se, pokud níže nevyplníte vlastní stránku.')
                    ->schema([
                        Fields::richText('description', 'Popis (perex)'),
                        MediaPicker::make('hero_image')
                            ->label('Hlavní obrázek')
                            ->acceptedFileTypes(['image/*']),
                    ]),
                Section::make('Vlastní stránka (přepíše výchozí rozvržení)')
                    ->description('Sestavte vlastní vzhled z bloků. Jakmile přidáte obsah, nahradí výchozí rozvržení této kategorie.')
                    ->relationship('customPage', condition: fn (?array $state): bool => filled($state['content'] ?? null))
                    ->mutateRelationshipDataBeforeCreateUsing(fn (array $data, ServiceCategory $record): array => [
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
}
