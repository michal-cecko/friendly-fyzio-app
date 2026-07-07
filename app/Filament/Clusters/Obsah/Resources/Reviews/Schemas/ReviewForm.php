<?php

namespace App\Filament\Clusters\Obsah\Resources\Reviews\Schemas;

use App\Models\Course;
use App\Models\Service;
use App\Models\Workshop;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use RalphJSmit\Filament\MediaLibrary\Filament\Forms\Components\MediaPicker;

class ReviewForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Recenze')
                    ->columns(2)
                    ->schema([
                        ToggleButtons::make('rating')
                            ->label('Hodnocení')
                            ->options([
                                1 => '1',
                                2 => '2',
                                3 => '3',
                                4 => '4',
                                5 => '5',
                            ])
                            ->inline()
                            ->default(5)
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('author_name')
                            ->label('Jméno autora')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('author_role')
                            ->label('Role / popisek')
                            ->helperText('Např. „účastnice kurzu“ nebo „klientka“.')
                            ->maxLength(255),
                        Textarea::make('content')
                            ->label('Text recenze')
                            ->required()
                            ->rows(5)
                            ->columnSpanFull(),
                        MorphToSelect::make('reviewable')
                            ->label('Vztahuje se k (nepovinné)')
                            ->types([
                                MorphToSelect\Type::make(Course::class)
                                    ->titleAttribute('name')
                                    ->label('Kurz'),
                                MorphToSelect\Type::make(Workshop::class)
                                    ->titleAttribute('name')
                                    ->label('Workshop'),
                                MorphToSelect\Type::make(Service::class)
                                    ->titleAttribute('name')
                                    ->label('Služba'),
                            ])
                            ->searchable()
                            ->columnSpanFull(),
                        MediaPicker::make('photo')
                            ->label('Fotka autora (nepovinné)')
                            ->acceptedFileTypes(['image/*']),
                        Toggle::make('visible')
                            ->label('Zveřejnit na webu')
                            ->default(true),
                    ]),
            ]);
    }
}
