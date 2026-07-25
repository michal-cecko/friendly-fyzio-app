<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseSeries\Schemas;

use App\Enums\CourseSeriesStatus;
use App\Enums\CourseSeriesVisibility;
use App\Enums\WaitlistPromotionMode;
use App\Filament\Support\Schemas\PresenceBanner;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;

class CourseSeriesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                PresenceBanner::make(),
                Select::make('course_id')
                    ->label('Kurz')
                    ->relationship('course', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required()
                    ->hidden(fn ($livewire): bool => $livewire instanceof RelationManager),
                TextInput::make('name')
                    ->label('Název')
                    ->required()
                    ->maxLength(255),
                TextInput::make('invoice_title')
                    ->label('Název pro fakturaci')
                    ->maxLength(255)
                    ->helperText('Použije se na fakturách a v e-mailech místo názvu kurzu.')
                    ->columnSpanFull(),
                DatePicker::make('start_date')
                    ->label('Začátek')
                    ->native(false)
                    ->required(),
                DatePicker::make('end_date')
                    ->label('Konec')
                    ->native(false)
                    ->required()
                    ->afterOrEqual('start_date'),
                TextInput::make('capacity')
                    ->label('Kapacita')
                    ->integer()
                    ->minValue(1)
                    ->required(),
                Radio::make('waitlist_promotion_mode')
                    ->label('Když se uvolní místo')
                    ->options(WaitlistPromotionMode::class)
                    ->descriptions(WaitlistPromotionMode::descriptions())
                    ->default(WaitlistPromotionMode::AutomaticAdd)
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('price')
                    ->label('Cena')
                    ->integer()
                    ->minValue(0)
                    ->suffix('Kč')
                    ->required(),
                ToggleButtons::make('status')
                    ->label('Stav')
                    ->options(CourseSeriesStatus::class)
                    ->default(CourseSeriesStatus::Open)
                    ->inline()
                    ->required(),
                ToggleButtons::make('visibility')
                    ->label('Viditelnost')
                    ->options(CourseSeriesVisibility::class)
                    ->default(CourseSeriesVisibility::Public)
                    ->inline()
                    ->required()
                    ->helperText('Soukromá série se na webu nikde nezobrazuje — přihlásit se lze jen přes přihlašovací odkaz. Ten (i pozvánky) je proto dostupný jen u soukromé série; u veřejné se tlačítko nezobrazuje.'),
            ]);
    }
}
