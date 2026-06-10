<?php

namespace App\Filament\Resources\CourseEnrollments;

use App\Filament\Resources\CourseEnrollments\Pages\CreateCourseEnrollment;
use App\Filament\Resources\CourseEnrollments\Pages\EditCourseEnrollment;
use App\Filament\Resources\CourseEnrollments\Pages\ListCourseEnrollments;
use App\Filament\Resources\CourseEnrollments\Pages\ViewCourseEnrollment;
use App\Filament\Resources\CourseEnrollments\Schemas\CourseEnrollmentForm;
use App\Filament\Resources\CourseEnrollments\Schemas\CourseEnrollmentInfolist;
use App\Filament\Resources\CourseEnrollments\Tables\CourseEnrollmentsTable;
use App\Models\CourseEnrollment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CourseEnrollmentResource extends Resource
{
    protected static ?string $model = CourseEnrollment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Kurzy';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'id';

    public static function getModelLabel(): string
    {
        return 'přihláška';
    }

    public static function getPluralModelLabel(): string
    {
        return 'přihlášky';
    }

    public static function getNavigationLabel(): string
    {
        return 'Přihlášky';
    }

    public static function form(Schema $schema): Schema
    {
        return CourseEnrollmentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CourseEnrollmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CourseEnrollmentsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['client', 'series.course']);
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
            'index' => ListCourseEnrollments::route('/'),
            'create' => CreateCourseEnrollment::route('/create'),
            'view' => ViewCourseEnrollment::route('/{record}'),
            'edit' => EditCourseEnrollment::route('/{record}/edit'),
        ];
    }
}
