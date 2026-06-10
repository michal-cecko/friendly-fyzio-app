<?php

namespace App\Filament\Resources\OneTimeLessons\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class OneTimeLessonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('course_id')
                    ->label('Kurz')
                    ->relationship('course', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(),
                Select::make('instructor_id')
                    ->label('Lektor')
                    ->relationship('instructor', 'name', fn (Builder $query): Builder => $query->whereIn('role', [UserRole::Admin, UserRole::Therapist]))
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(),
                Select::make('room_id')
                    ->label('Místnost')
                    ->relationship('room', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(),
                DatePicker::make('lesson_date')
                    ->label('Datum')
                    ->native(false)
                    ->required(),
                TimePicker::make('start_time')
                    ->label('Od')
                    ->native(false)
                    ->seconds(false)
                    ->required(),
                TimePicker::make('end_time')
                    ->label('Do')
                    ->native(false)
                    ->seconds(false)
                    ->required(),
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
            ]);
    }
}
