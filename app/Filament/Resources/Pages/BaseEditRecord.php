<?php

namespace App\Filament\Resources\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Alignment;

/**
 * App-wide base for resource edit pages. Drops the "Zrušit" (Cancel) action —
 * it only navigates away and adds noise — and right-aligns the remaining form
 * actions (Save). All edit pages should extend this instead of Filament's
 * {@see EditRecord} directly.
 */
abstract class BaseEditRecord extends EditRecord
{
    /**
     * @return array<Action | ActionGroup>
     */
    protected function getFormActions(): array
    {
        return array_values(array_filter(
            parent::getFormActions(),
            fn (Action|ActionGroup $action): bool => $action->getName() !== 'cancel',
        ));
    }

    public function getFormActionsAlignment(): string|Alignment
    {
        return Alignment::End;
    }

    /**
     * A header-placed Save button that submits the form, so Save is reachable
     * both above and below a long form. Uses a distinct name from the bottom
     * "save" form action to avoid a name collision within the page.
     */
    protected function getSaveHeaderAction(): Action
    {
        return Action::make('saveHeader')
            ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
            // Match the diskette icon applied app-wide to the bottom save button
            // (see AppServiceProvider); this action's distinct name skips that hook.
            ->icon('lucide-save')
            ->keyBindings(['mod+s'])
            ->action('save');
    }
}
