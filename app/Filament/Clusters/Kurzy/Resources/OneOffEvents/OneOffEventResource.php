<?php

namespace App\Filament\Clusters\Kurzy\Resources\OneOffEvents;

use App\Filament\Clusters\Kurzy\KurzyCluster;
use App\Filament\Clusters\Kurzy\Resources\OneOffEvents\Pages\CreateOneOffEvent;
use App\Filament\Clusters\Kurzy\Resources\OneOffEvents\Pages\EditOneOffEvent;
use App\Filament\Clusters\Kurzy\Resources\OneOffEvents\Pages\ListOneOffEvents;
use App\Filament\Clusters\Kurzy\Resources\OneOffEvents\Pages\ViewOneOffEvent;
use App\Filament\Clusters\Kurzy\Resources\OneOffEvents\Schemas\OneOffEventForm;
use App\Filament\Clusters\Kurzy\Resources\OneOffEvents\Schemas\OneOffEventInfolist;
use App\Filament\Clusters\Kurzy\Resources\OneOffEvents\Tables\OneOffEventsTable;
use App\Filament\Support\Concerns\ScopedToTherapist;
use App\Filament\Support\RelationManagers\OneOffEventBookingsRelationManager;
use App\Filament\Support\RelationManagers\WaitlistEntriesRelationManager;
use App\Models\OneOffEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OneOffEventResource extends Resource
{
    use ScopedToTherapist;

    protected static ?string $model = OneOffEvent::class;

    protected static ?string $cluster = KurzyCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'jednorázová akce';
    }

    public static function getPluralModelLabel(): string
    {
        return 'jednorázové akce';
    }

    public static function getNavigationLabel(): string
    {
        return 'Jednorázové akce';
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug', 'category.name', 'course.name'];
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var OneOffEvent $record */
        return array_filter([
            'Kategorie' => $record->category?->name,
            'Termín' => $record->event_date?->format('j. n. Y'),
            'Čas' => $record->start_time ? substr($record->start_time, 0, 5) : null,
            'Lektor' => $record->instructor?->name,
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return OneOffEventForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OneOffEventInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OneOffEventsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['category', 'course', 'instructor', 'room'])
            ->when(static::therapistUserScopeId(), fn (Builder $query, string $id) => $query->where('instructor_id', $id));
    }

    public static function getRelations(): array
    {
        return [
            OneOffEventBookingsRelationManager::class,
            WaitlistEntriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOneOffEvents::route('/'),
            'create' => CreateOneOffEvent::route('/create'),
            'view' => ViewOneOffEvent::route('/{record}'),
            'edit' => EditOneOffEvent::route('/{record}/edit'),
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
