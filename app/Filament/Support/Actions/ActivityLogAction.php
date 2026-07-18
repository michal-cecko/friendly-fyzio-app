<?php

namespace App\Filament\Support\Actions;

use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * A header action for any resource record that opens its activity log
 * (create / update / delete / restore history) in a modal. Add to a page's
 * getHeaderActions(): ActivityLogAction::make().
 */
class ActivityLogAction
{
    public static function make(): Action
    {
        return Action::make('activityLog')
            ->label('Historie změn')
            ->icon(Heroicon::OutlinedClock)
            ->color('gray')
            ->modalHeading('Historie změn')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Zavřít')
            ->modalContent(fn (Model $record) => view('filament.activity.record-log', [
                'activities' => method_exists($record, 'activitiesAsSubject')
                    ? $record->activitiesAsSubject()->with('causer')->latest('id')->limit(50)->get()
                    : collect(),
            ]));
    }
}
