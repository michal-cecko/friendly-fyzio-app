<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseLessons;

use App\Filament\Clusters\Kurzy\KurzyCluster;
use App\Filament\Clusters\Kurzy\Resources\CourseLessons\Pages\CreateCourseLesson;
use App\Filament\Clusters\Kurzy\Resources\CourseLessons\Pages\EditCourseLesson;
use App\Filament\Clusters\Kurzy\Resources\CourseLessons\Pages\ListCourseLessons;
use App\Filament\Clusters\Kurzy\Resources\CourseLessons\Pages\ViewCourseLesson;
use App\Filament\Clusters\Kurzy\Resources\CourseLessons\Schemas\CourseLessonForm;
use App\Filament\Clusters\Kurzy\Resources\CourseLessons\Schemas\CourseLessonInfolist;
use App\Filament\Clusters\Kurzy\Resources\CourseLessons\Tables\CourseLessonsTable;
use App\Models\CourseLesson;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CourseLessonResource extends Resource
{
    protected static ?string $model = CourseLesson::class;

    protected static ?string $cluster = KurzyCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'lesson_date';

    public static function getModelLabel(): string
    {
        return 'lekce kurzu';
    }

    public static function getPluralModelLabel(): string
    {
        return 'lekce kurzů';
    }

    public static function getNavigationLabel(): string
    {
        return 'Lekce kurzů';
    }

    public static function form(Schema $schema): Schema
    {
        return CourseLessonForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CourseLessonInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CourseLessonsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['series.course', 'instructor', 'room']);
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
            'index' => ListCourseLessons::route('/'),
            'create' => CreateCourseLesson::route('/create'),
            'view' => ViewCourseLesson::route('/{record}'),
            'edit' => EditCourseLesson::route('/{record}/edit'),
        ];
    }
}
