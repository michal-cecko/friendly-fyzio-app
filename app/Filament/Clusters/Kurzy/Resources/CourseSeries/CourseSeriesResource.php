<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseSeries;

use App\Filament\Clusters\Kurzy\KurzyCluster;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages\CreateCourseSeries;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages\EditCourseSeries;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages\ListCourseSeries;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages\ViewCourseSeries;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\Schemas\CourseSeriesForm;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\Schemas\CourseSeriesInfolist;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\Tables\CourseSeriesTable;
use App\Filament\Support\RelationManagers\CourseSeriesEnrollmentsRelationManager;
use App\Filament\Support\RelationManagers\WaitlistEntriesRelationManager;
use App\Models\CourseSeries;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CourseSeriesResource extends Resource
{
    protected static ?string $model = CourseSeries::class;

    protected static ?string $cluster = KurzyCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'běh kurzu';
    }

    public static function getPluralModelLabel(): string
    {
        return 'běhy kurzů';
    }

    public static function getNavigationLabel(): string
    {
        return 'Běhy kurzů';
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'course.name'];
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var CourseSeries $record */
        return array_filter([
            'Kurz' => $record->course?->name,
            'Zahájení' => $record->start_date?->format('j. n. Y'),
            'Stav' => $record->status?->getLabel(),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return CourseSeriesForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CourseSeriesInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CourseSeriesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['course']);
    }

    public static function getRelations(): array
    {
        return [
            CourseSeriesEnrollmentsRelationManager::class,
            WaitlistEntriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCourseSeries::route('/'),
            'create' => CreateCourseSeries::route('/create'),
            'view' => ViewCourseSeries::route('/{record}'),
            'edit' => EditCourseSeries::route('/{record}/edit'),
        ];
    }
}
