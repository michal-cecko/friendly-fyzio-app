<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseEnrollments;

use App\Filament\Clusters\Kurzy\KurzyCluster;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\Pages\CreateCourseEnrollment;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\Pages\EditCourseEnrollment;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\Pages\ListCourseEnrollments;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\Pages\ViewCourseEnrollment;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\Schemas\CourseEnrollmentForm;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\Schemas\CourseEnrollmentInfolist;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\Tables\CourseEnrollmentsTable;
use App\Models\CourseEnrollment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CourseEnrollmentResource extends Resource
{
    protected static ?string $model = CourseEnrollment::class;

    protected static ?string $cluster = KurzyCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 5;

    protected static int $globalSearchResultsLimit = 10;

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

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['client.name', 'client.email', 'series.name', 'series.course.name'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        /** @var CourseEnrollment $record */
        return trim(($record->client?->name ?? 'Neznámý klient').' — '.($record->series?->name ?? 'Neznámý běh'));
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var CourseEnrollment $record */
        return array_filter([
            'Kurz' => $record->series?->course?->name,
            'Stav' => $record->status?->getLabel(),
            'Platba' => $record->payment_status?->getLabel(),
        ]);
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
