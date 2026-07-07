<?php

namespace App\Filament\Clusters\Kurzy\Resources\Courses\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CourseForm
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
                Select::make('category_id')
                    ->label('Kategorie')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),
                Select::make('instructor_id')
                    ->label('Lektor')
                    ->relationship('instructor', 'name', fn (Builder $query): Builder => $query->whereIn('role', [UserRole::Admin, UserRole::Therapist]))
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(),
                Textarea::make('description')
                    ->label('Popis')
                    ->rows(4)
                    ->columnSpanFull(),
                TextInput::make('questionnaire_url')
                    ->label('Odkaz na dotazník recenzí')
                    ->helperText('Nechte prázdné pro použití výchozího odkazu z Nastavení → Recenze.')
                    ->url()
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('max_substitutions')
                    ->label('Max. náhrad')
                    ->integer()
                    ->minValue(0)
                    ->default(0),
                TextInput::make('early_cancel_hours')
                    ->label('Včasné zrušení (hodin předem)')
                    ->integer()
                    ->minValue(0)
                    ->default(24)
                    ->suffix('h'),
                DateTimePicker::make('published_at')
                    ->label('Publikováno')
                    ->native(false),
            ]);
    }
}
