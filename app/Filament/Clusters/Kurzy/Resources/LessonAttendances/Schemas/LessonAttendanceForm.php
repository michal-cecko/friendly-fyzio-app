<?php

namespace App\Filament\Clusters\Kurzy\Resources\LessonAttendances\Schemas;

use App\Models\CourseEnrollment;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class LessonAttendanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('enrollment_id')
                    ->label('Přihláška')
                    ->relationship('enrollment', 'id')
                    ->getOptionLabelFromRecordUsing(fn (CourseEnrollment $record): ?string => $record->client?->name)
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(),
                Select::make('lesson_id')
                    ->label('Lekce')
                    ->relationship('lesson', 'lesson_date')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(),
                Toggle::make('attended')
                    ->label('Účast')
                    ->default(false),
                DateTimePicker::make('cancelled_at')
                    ->label('Zrušeno')
                    ->native(false),
                Toggle::make('token_generated')
                    ->label('Token vygenerován')
                    ->default(false),
            ]);
    }
}
