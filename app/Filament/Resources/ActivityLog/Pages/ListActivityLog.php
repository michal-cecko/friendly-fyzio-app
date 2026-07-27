<?php

namespace App\Filament\Resources\ActivityLog\Pages;

use App\Filament\Resources\ActivityLog\ActivityLogResource;
use App\Models\User;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class ListActivityLog extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;

    /**
     * One tab per source of activity: everything, the system / online flow
     * (no authenticated causer), and one tab per user that has ever caused a
     * log entry.
     *
     * The per-user breakdown only makes sense for admins, who see every entry.
     * A pure therapist's list is already scoped to their own activity (see
     * ActivityLogResource::getEloquentQuery), so the tabs would both be
     * pointless and leak the names of every other causer — hide them entirely.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        if (! auth()->user()?->isAdmin()) {
            return [];
        }

        $tabs = [
            'all' => Tab::make('Vše'),
            'system' => Tab::make('Systém / online')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNull('causer_id'))
                ->badge(Activity::query()->whereNull('causer_id')->count()),
        ];

        $users = User::query()
            ->whereIn('id', Activity::query()->whereNotNull('causer_id')->distinct()->pluck('causer_id'))
            ->orderBy('name')
            ->pluck('name', 'id');

        foreach ($users as $id => $name) {
            $tabs['user_'.$id] = Tab::make($name)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('causer_id', $id))
                ->badge(Activity::query()->where('causer_id', $id)->count());
        }

        return $tabs;
    }
}
