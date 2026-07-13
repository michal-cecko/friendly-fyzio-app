<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseSeries\Schemas;

use App\Enums\CourseSeriesStatus;
use App\Filament\Support\Schemas\PresenceBanner;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
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
                    ->required(),
                TextInput::make('name')
                    ->label('Název')
                    ->required()
                    ->maxLength(255),
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
            ]);
    }
}
