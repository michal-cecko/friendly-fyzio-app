<?php

namespace App\Filament\Clusters\Provoz\Resources\Specializations\Schemas;

use App\Filament\Support\Schemas\ResponsiveColumns;
use App\Support\Icon;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Guava\IconPicker\Forms\Components\IconPicker;

class SpecializationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Specializace')
                    ->icon(Heroicon::OutlinedSparkles)
                    ->columnSpanFull()
                    ->columns(ResponsiveColumns::PAIR)
                    ->schema([
                        TextInput::make('name')
                            ->label('Název')
                            ->required()
                            ->maxLength(255),
                        IconPicker::make('icon')
                            ->label('Ikona')
                            ->sets(['lucide'])
                            ->searchable()
                            // Older rows stored bare lucide names („heart"), which
                            // the picker rejects as unknown icons.
                            ->formatStateUsing(fn (?string $state): ?string => Icon::name($state))
                            ->helperText('Zobrazuje se na kartě na profilu terapeuta.'),
                        Textarea::make('description')
                            ->label('Popis')
                            ->rows(2)
                            ->autosize()
                            ->columnSpanFull(),
                        Select::make('service_id')
                            ->label('Služba')
                            ->relationship('service', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Kam vede klik na tuto specializaci: otevře rezervaci s tímto terapeutem a touto službou. Bez služby se karta na profilu nezobrazuje.'),
                        TextInput::make('display_order')
                            ->label('Pořadí')
                            ->numeric()
                            ->default(0)
                            ->helperText('Nižší číslo = dříve v seznamu u služby.'),
                    ]),
            ]);
    }
}
