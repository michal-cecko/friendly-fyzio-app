<?php

namespace App\Filament\Resources\CourseCategories\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CourseCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
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
                    ->unique(ignoreRecord: true),
                Textarea::make('description')
                    ->label('Popis')
                    ->rows(4)
                    ->columnSpanFull(),
                TextInput::make('display_order')
                    ->label('Pořadí')
                    ->integer()
                    ->minValue(0)
                    ->default(0),
                DateTimePicker::make('published_at')
                    ->label('Publikováno')
                    ->native(false),
            ]);
    }
}
