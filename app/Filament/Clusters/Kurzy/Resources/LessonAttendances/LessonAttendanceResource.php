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
use App\Models\LessonAttendance;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LessonAttendanceResource extends Resource
{
    protected static ?string $model = LessonAttendance::class;

    protected static ?string $cluster = KurzyCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckCircle;

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'id';

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
        return parent::getEloquentQuery()->with(['enrollment.client', 'lesson.series.course']);
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
