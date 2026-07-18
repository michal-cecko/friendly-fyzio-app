<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseSeries\Schemas;

use App\Enums\CourseSeriesStatus;
use App\Enums\CourseSeriesVisibility;
use App\Filament\Support\Schemas\PresenceBanner;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                Toggle::make('auto_promote_waitlist')
                    ->label('Automaticky přidávat z čekací listiny')
                    ->helperText('Když se uvolní místo, systém sám osloví dalšího v pořadí. Vypněte, chcete-li přidávat z čekací listiny ručně.')
                    ->default(true)
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
                    ->helperText('Soukromý běh se na webu nikde nezobrazuje — přihlásit se lze jen přes přihlašovací odkaz.'),
            ]);
    }
}
