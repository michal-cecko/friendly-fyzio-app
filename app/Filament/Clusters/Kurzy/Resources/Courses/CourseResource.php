<?php

namespace App\Filament\Clusters\Kurzy\Resources\Courses;

use App\Filament\Clusters\Kurzy\KurzyCluster;
use App\Filament\Clusters\Kurzy\Resources\Courses\Pages\CreateCourse;
use App\Filament\Clusters\Kurzy\Resources\Courses\Pages\EditCourse;
use App\Filament\Clusters\Kurzy\Resources\Courses\Pages\ListCourses;
use App\Filament\Clusters\Kurzy\Resources\Courses\Pages\ViewCourse;
use App\Filament\Clusters\Kurzy\Resources\Courses\Schemas\CourseForm;
use App\Filament\Clusters\Kurzy\Resources\Courses\Schemas\CourseInfolist;
use App\Filament\Clusters\Kurzy\Resources\Courses\Tables\CoursesTable;
use App\Models\Course;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CourseResource extends Resource
{
    protected static ?string $model = Course::class;

    protected static ?string $cluster = KurzyCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'kurz';
    }

    public static function getPluralModelLabel(): string
    {
        return 'kurzy';
    }

    public static function getNavigationLabel(): string
    {
        return 'Kurzy';
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug'];
    }

    public static function form(Schema $schema): Schema
    {
        return CourseForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CourseInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CoursesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['category', 'instructor']);
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
            'index' => ListCourses::route('/'),
            'create' => CreateCourse::route('/create'),
            'view' => ViewCourse::route('/{record}'),
            'edit' => EditCourse::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
