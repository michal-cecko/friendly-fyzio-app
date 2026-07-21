<?php

namespace App\Filament\Resources\ActivityLog;

use App\Enums\UserRole;
use App\Filament\Resources\ActivityLog\Pages\ListActivityLog;
use App\Filament\Resources\ActivityLog\Pages\ViewActivityLog;
use App\Filament\Resources\ActivityLog\Schemas\ActivityLogInfolist;
use App\Filament\Resources\ActivityLog\Tables\ActivityLogTable;
use App\Models\Course;
use App\Models\Reservation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?int $navigationSort = 90;

    public static function getModelLabel(): string
    {
        return 'záznam aktivity';
    }

    public static function getPluralModelLabel(): string
    {
        return 'záznamy aktivity';
    }

    public static function getNavigationLabel(): string
    {
        return 'Záznamy aktivity';
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isStaff();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = Activity::query()->with(['subject', 'causer']);

        $user = auth()->user();

        // Admins see everything; a pure therapist sees only what they touched or
        // what belongs to them (their reservations and the courses they teach).
        if ($user?->role === UserRole::Admin) {
            return $query;
        }

        $profileId = $user?->staffProfile?->getKey();

        return $query->where(function (Builder $scope) use ($user, $profileId): void {
            $scope->where('causer_id', $user?->getKey());

            if ($profileId !== null) {
                $scope->orWhere(fn (Builder $q): Builder => $q
                    ->where('subject_type', 'reservation')
                    ->whereIn('subject_id', Reservation::withTrashed()->where('therapist_id', $profileId)->select('id')));
            }

            $scope->orWhere(fn (Builder $q): Builder => $q
                ->where('subject_type', 'course')
                ->whereIn('subject_id', Course::withTrashed()->where('instructor_id', $user?->getKey())->select('id')));
        });
    }

    public static function table(Table $table): Table
    {
        return ActivityLogTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ActivityLogInfolist::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityLog::route('/'),
            'view' => ViewActivityLog::route('/{record}'),
        ];
    }
}
