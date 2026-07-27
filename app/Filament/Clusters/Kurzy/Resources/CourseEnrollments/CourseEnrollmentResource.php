<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseEnrollments;

use App\Filament\Clusters\Kurzy\KurzyCluster;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\Pages\CreateCourseEnrollment;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\Pages\ListCourseEnrollments;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\Pages\ViewCourseEnrollment;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\Schemas\CourseEnrollmentForm;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\Schemas\CourseEnrollmentInfolist;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\Tables\CourseEnrollmentsTable;
use App\Filament\Support\Concerns\ScopedToTherapist;
use App\Filament\Support\RelationManagers\PaymentsRelationManager;
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
    use ScopedToTherapist;

    protected static ?string $model = CourseEnrollment::class;

    protected static ?string $cluster = KurzyCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 5;

    protected static bool $shouldRegisterNavigation = false;

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
     * Record titles are the object of modal headings ("Smazat přihlášku Jana Nováka"),
     * so they are written in the accusative.
     */
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        /** @var ?CourseEnrollment $record */
        return trim('přihlášku '.($record?->client?->name ?? ''));
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
        return trim(($record->client?->name ?? 'Neznámý klient').' — '.($record->series?->name ?? 'Neznámá série'));
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
        return parent::getEloquentQuery()->with(['client', 'series.course'])
            ->when(static::therapistUserScopeId(), fn (Builder $query, string $id) => $query
                ->whereHas('series.course', fn (Builder $course) => $course->where('instructor_id', $id)));
    }

    public static function getRelations(): array
    {
        return [
            PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCourseEnrollments::route('/'),
            'create' => CreateCourseEnrollment::route('/create'),
            'view' => ViewCourseEnrollment::route('/{record}'),
        ];
    }
}
