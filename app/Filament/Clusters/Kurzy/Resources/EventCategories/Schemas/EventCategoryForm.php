<?php

namespace App\Filament\Clusters\Kurzy\Resources\EventCategories\Schemas;

use App\Filament\Support\Schemas\PresenceBanner;
use App\Mason\BrickRegistry;
use App\Models\EventCategory;
use Awcodes\Mason\Mason;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use RalphJSmit\Filament\MediaLibrary\Filament\Forms\Components\MediaPicker;

class EventCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                PresenceBanner::make(),
                TextInput::make('name')
                    ->label('Název')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),
                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->helperText('Kategorie má vlastní veřejnou stránku na adrese /slug.'),
                Textarea::make('description')
                    ->label('Popis')
                    ->rows(4)
                    ->columnSpanFull(),
                MediaPicker::make('featured_image')
                    ->label('Fotka')
                    ->acceptedFileTypes(['image/*'])
                    ->helperText('Zobrazuje se v hlavičce veřejné stránky kategorie.')
                    ->columnSpanFull(),
                TextInput::make('display_order')
                    ->label('Pořadí')
                    ->integer()
                    ->minValue(0)
                    ->default(0),
                DateTimePicker::make('published_at')
                    ->label('Publikováno')
                    ->native(false)
                    ->helperText('Bez data (nebo budoucí datum) = skryté, viditelné jen pro administrátory.'),
                Section::make('Vlastní stránka (přepíše výchozí rozvržení)')
                    ->description('Sestavte vlastní vzhled z bloků. Jakmile přidáte obsah, nahradí výchozí rozvržení této kategorie.')
                    ->columnSpanFull()
                    ->relationship('customPage', condition: fn (?array $state): bool => filled($state['content'] ?? null))
                    ->mutateRelationshipDataBeforeCreateUsing(fn (array $data, EventCategory $record): array => [
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
