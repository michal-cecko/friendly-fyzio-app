<?php

namespace App\Filament\Resources\OneTimeLessons;

use App\Filament\Resources\OneTimeLessons\Pages\CreateOneTimeLesson;
use App\Filament\Resources\OneTimeLessons\Pages\EditOneTimeLesson;
use App\Filament\Resources\OneTimeLessons\Pages\ListOneTimeLessons;
use App\Filament\Resources\OneTimeLessons\Pages\ViewOneTimeLesson;
use App\Filament\Resources\OneTimeLessons\Schemas\OneTimeLessonForm;
use App\Filament\Resources\OneTimeLessons\Schemas\OneTimeLessonInfolist;
use App\Filament\Resources\OneTimeLessons\Tables\OneTimeLessonsTable;
use App\Models\OneTimeLesson;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class OneTimeLessonResource extends Resource
{
    protected static ?string $model = OneTimeLesson::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|UnitEnum|null $navigationGroup = 'Jednorázové lekce';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'lesson_date';

    public static function getModelLabel(): string
    {
        return 'jednorázová lekce';
    }

    public static function getPluralModelLabel(): string
    {
        return 'jednorázové lekce';
    }

    public static function getNavigationLabel(): string
    {
        return 'Jednorázové lekce';
    }

    public static function form(Schema $schema): Schema
    {
        return OneTimeLessonForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OneTimeLessonInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OneTimeLessonsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['course', 'instructor', 'room']);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOneTimeLessons::route('/'),
            'create' => CreateOneTimeLesson::route('/create'),
            'view' => ViewOneTimeLesson::route('/{record}'),
            'edit' => EditOneTimeLesson::route('/{record}/edit'),
        ];
    }
}
