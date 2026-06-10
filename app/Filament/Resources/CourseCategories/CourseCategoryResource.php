<?php

namespace App\Filament\Resources\CourseCategories;

use App\Filament\Resources\CourseCategories\Pages\CreateCourseCategory;
use App\Filament\Resources\CourseCategories\Pages\EditCourseCategory;
use App\Filament\Resources\CourseCategories\Pages\ListCourseCategories;
use App\Filament\Resources\CourseCategories\Pages\ViewCourseCategory;
use App\Filament\Resources\CourseCategories\Schemas\CourseCategoryForm;
use App\Filament\Resources\CourseCategories\Schemas\CourseCategoryInfolist;
use App\Filament\Resources\CourseCategories\Tables\CourseCategoriesTable;
use App\Models\CourseCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CourseCategoryResource extends Resource
{
    protected static ?string $model = CourseCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Kurzy';

    protected static ?int $navigationSort = 2;

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
