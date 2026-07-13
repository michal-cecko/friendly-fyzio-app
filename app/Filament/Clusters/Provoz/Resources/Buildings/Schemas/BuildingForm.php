<?php

namespace App\Filament\Clusters\Provoz\Resources\Buildings\Schemas;

use App\Filament\Support\Schemas\PresenceBanner;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class BuildingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                PresenceBanner::make(),
                Section::make('Informace o budově')
                    ->icon(Heroicon::OutlinedBuildingLibrary)
                    ->columns(['default' => 1, 'sm' => 2])
                    ->schema([
                        TextInput::make('name')
                            ->label('Název')
                            ->placeholder('Např. Hlavní budova')
                            ->prefixIcon(Heroicon::OutlinedBuildingLibrary)
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(1),
                        TextInput::make('address')
                            ->label('Adresa')
                            ->placeholder('Ulice, č.p., město, PSČ')
                            ->helperText('Celá adresa včetně města a PSČ.')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(1),
                    ]),
            ]);
    }
}
