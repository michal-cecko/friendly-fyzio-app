<?php

namespace App\Filament\Clusters\Provoz\Resources\Rooms\Schemas;

use App\Filament\Clusters\Provoz\Resources\Buildings\Schemas\BuildingForm;
use App\Filament\Support\Schemas\PresenceBanner;
use App\Filament\Support\Schemas\RecordTimestamps;
use App\Filament\Support\Schemas\ResponsiveColumns;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class RoomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                PresenceBanner::make(),
                Section::make('Místnost')
                    ->icon(Heroicon::OutlinedRectangleGroup)
                    ->gridContainer()
                    ->columns(ResponsiveColumns::PAIR)
                    ->schema([
                        Select::make('building_id')
                            ->label('Budova')
                            ->relationship('building', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->createOptionForm(fn (Schema $schema): Schema => BuildingForm::configure($schema)),
                        TextInput::make('name')
                            ->label('Název')
                            ->required()
                            ->maxLength(255),
                        RecordTimestamps::entries(),
                    ]),
            ]);
    }
}
