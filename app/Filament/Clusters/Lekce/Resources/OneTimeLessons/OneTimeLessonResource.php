<?php

namespace App\Filament\Clusters\Lekce\Resources\OneTimeLessons;

use App\Filament\Clusters\Lekce\LekceCluster;
use App\Filament\Clusters\Lekce\Resources\OneTimeLessons\Pages\CreateOneTimeLesson;
use App\Filament\Clusters\Lekce\Resources\OneTimeLessons\Pages\EditOneTimeLesson;
use App\Filament\Clusters\Lekce\Resources\OneTimeLessons\Pages\ListOneTimeLessons;
use App\Filament\Clusters\Lekce\Resources\OneTimeLessons\Pages\ViewOneTimeLesson;
use App\Filament\Clusters\Lekce\Resources\OneTimeLessons\Schemas\OneTimeLessonForm;
use App\Filament\Clusters\Lekce\Resources\OneTimeLessons\Schemas\OneTimeLessonInfolist;
use App\Filament\Clusters\Lekce\Resources\OneTimeLessons\Tables\OneTimeLessonsTable;
use App\Filament\Support\Concerns\ScopedToTherapist;
use App\Filament\Support\RelationManagers\OneTimeLessonBookingsRelationManager;
use App\Filament\Support\RelationManagers\WaitlistEntriesRelationManager;
use App\Models\OneTimeLesson;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OneTimeLessonResource extends Resource
{
    use ScopedToTherapist;

    protected static ?string $model = OneTimeLesson::class;

    protected static ?string $cluster = LekceCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'lesson_date';

    public static function getModelLabel(): string
    {
        return 'jednorázová lekce';
    }

    public static function getPluralModelLabel(): string
    {
        return 'jednorázové lekce';
    }

    public static function getNavigationLabel(): string
    {
        return 'Jednorázové lekce';
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['course.name'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        /** @var OneTimeLesson $record */
        return trim(($record->course?->name ?? 'Neznámý kurz').' — '.($record->lesson_date?->format('j. n. Y') ?? 'neznámé datum'));
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var OneTimeLesson $record */
        return array_filter([
            'Čas' => $record->start_time ? substr($record->start_time, 0, 5) : null,
            'Lektor' => $record->instructor?->name,
            'Místnost' => $record->room?->name,
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return OneTimeLessonForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OneTimeLessonInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OneTimeLessonsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['course', 'instructor', 'room'])
            ->when(static::therapistUserScopeId(), fn (Builder $query, string $id) => $query->where('instructor_id', $id));
    }

    public static function getRelations(): array
    {
        return [
            OneTimeLessonBookingsRelationManager::class,
            WaitlistEntriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOneTimeLessons::route('/'),
            'create' => CreateOneTimeLesson::route('/create'),
            'view' => ViewOneTimeLesson::route('/{record}'),
            'edit' => EditOneTimeLesson::route('/{record}/edit'),
        ];
    }
}
