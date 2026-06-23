<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseLessons\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class CourseLessonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('series_id')
                    ->label('Běh')
                    ->relationship('series', 'name')
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
            ]);
    }
}
