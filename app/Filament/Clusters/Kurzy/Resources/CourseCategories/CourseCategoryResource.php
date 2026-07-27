<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseCategories;

use App\Filament\Clusters\Kurzy\KurzyCluster;
use App\Filament\Clusters\Kurzy\Resources\CourseCategories\Pages\CreateCourseCategory;
use App\Filament\Clusters\Kurzy\Resources\CourseCategories\Pages\EditCourseCategory;
use App\Filament\Clusters\Kurzy\Resources\CourseCategories\Pages\ListCourseCategories;
use App\Filament\Clusters\Kurzy\Resources\CourseCategories\Pages\ViewCourseCategory;
use App\Filament\Clusters\Kurzy\Resources\CourseCategories\Schemas\CourseCategoryForm;
use App\Filament\Clusters\Kurzy\Resources\CourseCategories\Schemas\CourseCategoryInfolist;
use App\Filament\Clusters\Kurzy\Resources\CourseCategories\Tables\CourseCategoriesTable;
use App\Filament\Support\Concerns\RestrictedToLecturers;
use App\Models\CourseCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CourseCategoryResource extends Resource
{
    use RestrictedToLecturers;

    protected static ?string $model = CourseCategory::class;

    protected static ?string $cluster = KurzyCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'kategorie kurzu';
    }

    public static function getPluralModelLabel(): string
    {
        return 'kategorie kurzů';
    }

    public static function getNavigationLabel(): string
    {
        return 'Kategorie kurzů';
    }

    /**
     * The catalogue's shape is an administrative concern: below admin the sidebar
     * item only adds noise to a list nobody there will change. Access itself is
     * untouched — links from a course, breadcrumbs and direct URLs all still work.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
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
        return CourseCategoryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CourseCategoryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CourseCategoriesTable::configure($table);
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
            'index' => ListCourseCategories::route('/'),
            'create' => CreateCourseCategory::route('/create'),
            'view' => ViewCourseCategory::route('/{record}'),
            'edit' => EditCourseCategory::route('/{record}/edit'),
        ];
    }
}
