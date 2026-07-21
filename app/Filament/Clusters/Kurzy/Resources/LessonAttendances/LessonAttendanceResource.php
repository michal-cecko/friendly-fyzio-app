<?php

namespace App\Filament\Clusters\Kurzy\Resources\LessonAttendances;

use App\Filament\Clusters\Kurzy\KurzyCluster;
use App\Filament\Clusters\Kurzy\Resources\LessonAttendances\Pages\CreateLessonAttendance;
use App\Filament\Clusters\Kurzy\Resources\LessonAttendances\Pages\EditLessonAttendance;
use App\Filament\Clusters\Kurzy\Resources\LessonAttendances\Pages\ListLessonAttendances;
use App\Filament\Clusters\Kurzy\Resources\LessonAttendances\Pages\ViewLessonAttendance;
use App\Filament\Clusters\Kurzy\Resources\LessonAttendances\Schemas\LessonAttendanceForm;
use App\Filament\Clusters\Kurzy\Resources\LessonAttendances\Schemas\LessonAttendanceInfolist;
use App\Filament\Clusters\Kurzy\Resources\LessonAttendances\Tables\LessonAttendancesTable;
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

class LessonAttendanceResource extends Resource
{
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
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['enrollment.client.name', 'enrollment.client.email', 'lesson.series.name'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        /** @var LessonAttendance $record */
        return trim(($record->enrollment?->client?->name ?? 'Neznámý klient').' — '.($record->lesson?->lesson_date?->format('j. n. Y') ?? 'neznámé datum'));
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var LessonAttendance $record */
        return array_filter([
            'Kurz' => $record->lesson?->series?->course?->name,
            'Přítomen' => $record->attended ? 'Ano' : 'Ne',
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return LessonAttendanceForm::configure($schema);
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
        return parent::getEloquentQuery()->with(['enrollment.client', 'lesson.series.course'])
            ->when(static::therapistUserScopeId(), fn (Builder $query, string $id) => $query
                ->whereHas('lesson.series.course', fn (Builder $course) => $course->where('instructor_id', $id)));
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
            'create' => CreateLessonAttendance::route('/create'),
            'view' => ViewLessonAttendance::route('/{record}'),
            'edit' => EditLessonAttendance::route('/{record}/edit'),
        ];
    }
}
