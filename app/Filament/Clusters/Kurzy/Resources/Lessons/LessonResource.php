<?php

namespace App\Filament\Clusters\Kurzy\Resources\Lessons;

use App\Filament\Clusters\Kurzy\KurzyCluster;
use App\Filament\Clusters\Kurzy\Resources\Courses\CourseResource;
use App\Filament\Clusters\Kurzy\Resources\Lessons\Pages\CreateLesson;
use App\Filament\Clusters\Kurzy\Resources\Lessons\Pages\EditLesson;
use App\Filament\Clusters\Kurzy\Resources\Lessons\Pages\ListLessons;
use App\Filament\Clusters\Kurzy\Resources\Lessons\Pages\ViewLesson;
use App\Filament\Clusters\Kurzy\Resources\Lessons\RelationManagers\AttendancesRelationManager;
use App\Filament\Clusters\Kurzy\Resources\Lessons\Schemas\LessonForm;
use App\Filament\Clusters\Kurzy\Resources\Lessons\Schemas\LessonInfolist;
use App\Filament\Clusters\Kurzy\Resources\Lessons\Tables\LessonsTable;
use App\Filament\Support\Concerns\EscapesClusterNavigation;
use App\Filament\Support\Concerns\RestrictedToLecturers;
use App\Filament\Support\Concerns\ScopedToTherapist;
use App\Filament\Support\RelationManagers\LessonBookingsRelationManager;
use App\Filament\Support\RelationManagers\WaitlistEntriesRelationManager;
use App\Models\Lesson;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Every session in one place: the scheduled lessons of a course série and the
 * standalone workshops / jednorázové lekce, which are the same record since the
 * merge. What separates them is a série and whether they are on public sale.
 */
class LessonResource extends Resource
{
    use EscapesClusterNavigation;
    use RestrictedToLecturers;
    use ScopedToTherapist;

    protected static ?string $model = Lesson::class;

    protected static ?string $cluster = KurzyCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'lekce';
    }

    public static function getPluralModelLabel(): string
    {
        return 'lekce';
    }

    public static function getNavigationLabel(): string
    {
        return 'Lekce';
    }

    /**
     * Directly under Kurzy once both have left the cluster's sidebar entry.
     */
    public static function getEscapedNavigationSort(): ?int
    {
        return CourseResource::getEscapedNavigationSort() + 1;
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        /** @var Lesson|null $record */
        return $record?->displayName() ?? parent::getRecordTitle($record);
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug', 'category.name', 'course.name', 'series.name', 'series.course.name'];
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Lesson $record */
        return array_filter([
            'Kurz' => $record->offerCourse()?->name,
            'Série' => $record->series?->name,
            'Kategorie' => $record->category?->name,
            'Termín' => $record->lesson_date?->format('j. n. Y'),
            'Čas' => $record->start_time ? substr($record->start_time, 0, 5) : null,
            'Lektor' => $record->instructor?->name,
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return LessonForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LessonInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LessonsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['category', 'course', 'series.course', 'instructor', 'room'])
            ->withOccupancyCounts()
            ->when(static::therapistUserScopeId(), fn (Builder $query, string $id) => $query->ledBy($id));
    }

    public static function getRelations(): array
    {
        return [
            AttendancesRelationManager::class,
            LessonBookingsRelationManager::class,
            WaitlistEntriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLessons::route('/'),
            'create' => CreateLesson::route('/create'),
            'view' => ViewLesson::route('/{record}'),
            'edit' => EditLesson::route('/{record}/edit'),
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
