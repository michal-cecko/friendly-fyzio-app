<?php

namespace App\Filament\Support\Actions;

use Closure;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * A header action for any resource record that opens its activity log
 * (create / update / delete / restore history) in a modal. Add to a page's
 * getHeaderActions(): ActivityLogAction::make(). The modal body is the
 * RecordActivityLog Livewire component — search, filters and pagination live
 * there, so records that are not audited simply show the empty state.
 */
class ActivityLogAction
{
    /**
     * Some records answer for others: a lesson's history is not much use
     * without what happened to the people on its list, and those events are
     * filed against their own seats. Pass a closure returning
     * `[['type' => morph class, 'ids' => [...]], …]` to fold them in.
     *
     * @param  (Closure(Model): array<int, array{type: string, ids: array<int, string>}>)|null  $relatedSubjects
     */
    public static function make(?Closure $relatedSubjects = null): Action
    {
        return Action::make('activityLog')
            ->label('Historie změn')
            ->icon(Heroicon::OutlinedClock)
            ->color('gray')
            ->modalHeading('Historie změn')
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Zavřít')
            ->modalContent(fn (Model $record) => view('filament.activity.record-log-modal', [
                'subjectType' => $record->getMorphClass(),
                'subjectId' => (string) $record->getKey(),
                'relatedSubjects' => $relatedSubjects === null ? [] : $relatedSubjects($record),
            ]));
    }
}
