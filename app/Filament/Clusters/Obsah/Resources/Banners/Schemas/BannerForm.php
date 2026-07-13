<?php

namespace App\Filament\Clusters\Obsah\Resources\Banners\Schemas;

use App\Enums\BannerType;
use App\Filament\Support\Schemas\PresenceBanner;
use App\Mason\Support\LinkPickerField;
use App\Models\Page;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Guava\IconPicker\Forms\Components\IconPicker;
use RalphJSmit\Filament\MediaLibrary\Filament\Forms\Components\MediaPicker;

class BannerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                PresenceBanner::make(),
                Section::make('Banner')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Interní název')
                            ->required()
                            ->columnSpanFull(),
                        Select::make('type')
                            ->label('Typ')
                            ->options(BannerType::class)
                            ->default(BannerType::Topbar)
                            ->required()
                            ->live(),
                        Select::make('placement')
                            ->label('Umístění')
                            ->options(['all' => 'Všechny stránky', 'specific' => 'Konkrétní stránky'])
                            ->default('all')
                            ->required()
                            ->live(),
                        Select::make('page_ids')
                            ->label('Stránky')
                            ->multiple()
                            ->searchable()
                            ->options(fn (): array => Page::query()->orderBy('title')->pluck('title', 'id')->all())
                            ->visible(fn (Get $get): bool => $get('placement') === 'specific')
                            ->columnSpanFull(),
                    ]),

                Section::make('Obsah banneru')
                    ->columns(2)
                    ->schema([
                        TextInput::make('content.text')
                            ->label('Text')
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => self::isType($get, BannerType::Topbar)),
                        TextInput::make('content.title')
                            ->label('Nadpis')
                            ->visible(fn (Get $get): bool => self::isType($get, BannerType::Floating, BannerType::Popup)),
                        TextInput::make('content.badge_text')
                            ->label('Štítek')
                            ->visible(fn (Get $get): bool => self::isType($get, BannerType::Popup)),
                        Textarea::make('content.description')
                            ->label('Popis')
                            ->rows(2)
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => self::isType($get, BannerType::Floating, BannerType::Popup)),
                        IconPicker::make('content.icon')
                            ->label('Ikona')
                            ->sets(['heroicons'])
                            ->searchable()
                            ->visible(fn (Get $get): bool => self::isType($get, BannerType::Topbar, BannerType::Floating)),
                        ColorPicker::make('content.bg_color')
                            ->label('Barva pozadí')
                            ->visible(fn (Get $get): bool => self::isType($get, BannerType::Topbar, BannerType::Floating)),
                        MediaPicker::make('content.image')
                            ->label('Obrázek')
                            ->acceptedFileTypes(['image/*'])
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => self::isType($get, BannerType::Popup)),
                        LinkPickerField::make('content.cta_', 'Tlačítko', withText: true, withIcon: true)
                            ->columnSpanFull(),
                    ]),

                Section::make('Zobrazení')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Aktivní'),
                        TextInput::make('sort_order')
                            ->label('Priorita (vyšší = přednost)')
                            ->numeric()
                            ->default(0),
                        DateTimePicker::make('active_from')
                            ->label('Aktivní od'),
                        DateTimePicker::make('active_to')
                            ->label('Aktivní do'),
                    ]),
            ]);
    }

    protected static function isType(Get $get, BannerType ...$types): bool
    {
        $value = $get('type');
        $value = $value instanceof BannerType ? $value->value : $value;

        return in_array($value, array_map(fn (BannerType $type): string => $type->value, $types), true);
    }
}
