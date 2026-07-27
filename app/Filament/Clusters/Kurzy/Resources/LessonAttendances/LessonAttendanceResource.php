<?php

namespace App\Filament\Clusters\Kurzy\Resources\LessonAttendances;

use App\Filament\Clusters\Kurzy\KurzyCluster;
use App\Filament\Clusters\Kurzy\Resources\LessonAttendances\Pages\ListLessonAttendances;
use App\Filament\Clusters\Kurzy\Resources\LessonAttendances\Pages\ViewLessonAttendance;
use App\Filament\Clusters\Kurzy\Resources\LessonAttendances\Schemas\LessonAttendanceInfolist;
use App\Filament\Clusters\Kurzy\Resources\LessonAttendances\Tables\LessonAttendancesTable;
use App\Filament\Support\Actions\EditExcuseAction;
use App\Filament\Support\Actions\ToggleLessonAttendanceAction;
use App\Filament\Support\AttendancePresenter;
use App\Filament\Support\Concerns\RestrictedToLecturers;
use App\Filament\Support\Concerns\ScopedToTherapist;
use App\Models\LessonAttendance;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A seat on a lesson, on its own page — reached from the lesson's Docházka
 * section, from global search, or from a link in the activity log.
 *
 * Read-only by design: rows are created by the roster and by sign-ups, never by
 * hand, and the two things staff do change — who is coming, and why they are not
 * — go through {@see ToggleLessonAttendanceAction}
 * and {@see EditExcuseAction}, which keep the
 * náhrada engine in step. A free-form edit form could not.
 */
class LessonAttendanceResource extends Resource
{
    use RestrictedToLecturers;
    use ScopedToTherapist;

    protected static ?string $model = LessonAttendance::class;

    protected static ?string $cluster = KurzyCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckCircle;

    protected static ?int $navigationSort = 6;

    protected static bool $shouldRegisterNavigation = false;

    protected static int $globalSearchResultsLimit = 10;

    public static function getModelLabel(): string
    {
        return 'docházka';
    }

    public static function getPluralModelLabel(): string
    {
        return 'docházky';
    }

    public static function getNavigationLabel(): string
    {
        return 'Docházka';
    }

    /**
     * Record titles are the object of modal headings ("Smazat docházku Jany Novákové"),
     * so they are written in the accusative.
     */
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        /** @var ?LessonAttendance $record */
        return trim('docházku '.($record?->client?->name ?? ''));
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['client.name', 'client.email', 'lesson.series.name'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        /** @var LessonAttendance $record */
        return trim(($record->client?->name ?? 'Neznámý klient').' — '.($record->lesson?->lesson_date?->format('j. n. Y') ?? 'neznámé datum'));
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var LessonAttendance $record */
        return array_filter([
            'Kurz' => $record->lesson?->series?->course?->name,
            'Přihláška' => AttendancePresenter::originLabel($record),
            'Účast' => AttendancePresenter::presenceLabel($record),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LessonAttendanceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LessonAttendancesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['client', 'enrollment.series', 'booking', 'lesson.series.course'])
            ->when(static::therapistUserScopeId(), fn (Builder $query, string $id) => $query
                ->whereHas('lesson', fn (Builder $lesson) => $lesson->ledBy($id)));
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
            'index' => ListLessonAttendances::route('/'),
            'view' => ViewLessonAttendance::route('/{record}'),
        ];
    }
}
