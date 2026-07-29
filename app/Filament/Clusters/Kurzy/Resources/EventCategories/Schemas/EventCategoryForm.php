<?php

namespace App\Filament\Clusters\Kurzy\Resources\EventCategories\Schemas;

use App\Filament\Support\Actions\CopyPageContentAction;
use App\Filament\Support\Schemas\DerivedSlug;
use App\Filament\Support\Schemas\PresenceBanner;
use App\Mason\BrickRegistry;
use App\Models\EventCategory;
use Awcodes\Mason\Mason;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use RalphJSmit\Filament\MediaLibrary\Filament\Forms\Components\MediaPicker;

class EventCategoryForm
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
                    ->columns(['default' => 1, '@2xl' => 3])
                    ->schema([
                        MediaPicker::make('featured_image')
                            ->label('Fotka')
                            ->acceptedFileTypes(['image/*'])
                            ->helperText('Zobrazuje se v hlavičce veřejné stránky kategorie. Na šířku, ideálně alespoň 1200 px.')
                            ->columnSpan(1),
                        Grid::make(2)
                            ->columnSpan(['default' => 1, '@2xl' => 2])
                            ->schema([
                                TextInput::make('name')
                                    ->label('Název')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(DerivedSlug::syncFrom(EventCategory::class))
                                    ->helperText('Název kategorie tak, jak se zobrazí návštěvníkům.')
                                    ->columnSpanFull(),
                                DerivedSlug::field('Adresa veřejné stránky kategorie. Doplní se sama z názvu.')
                                    ->prefix(url('/').'/')
                                    ->columnSpanFull(),
                                Textarea::make('description')
                                    ->label('Popis')
                                    ->rows(4)
                                    ->helperText('Krátké představení kategorie v hlavičce její veřejné stránky.')
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
                    ]),
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
                            ->hintAction(CopyPageContentAction::make())
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
