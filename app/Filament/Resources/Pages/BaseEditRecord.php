<?php

namespace App\Filament\Resources\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Alignment;

/**
 * App-wide base for resource edit pages. Drops the "Zrušit" (Cancel) action —
 * it only navigates away and adds noise — right-aligns the remaining form
 * actions (Save), and mirrors a Save button into the page header so it is
 * reachable above a long form as well as below it. All edit pages should extend
 * this instead of Filament's {@see EditRecord} directly.
 */
abstract class BaseEditRecord extends EditRecord
{
    /**
     * Prepend a header-placed Save button to whatever header actions the
     * concrete page defines, so every edit page exposes Save at the top too,
     * and clarify the default "Zobrazit" (view) action label to "Zobrazit
     * detail". Done here (after the parent has cached the page's own actions)
     * so no concrete page needs to opt in via its {@see getHeaderActions()}.
     */
    public function cacheInteractsWithHeaderActions(): void
    {
        parent::cacheInteractsWithHeaderActions();

        $hasSaveHeaderAction = false;

        foreach ($this->cachedHeaderActions as $action) {
            if ($action instanceof Action) {
                if ($action->getName() === 'saveHeader') {
                    $hasSaveHeaderAction = true;
                }

                $this->relabelViewAction($action);

                continue;
            }

            // ViewAction may live inside a "Další akce" dropdown group.
            foreach ($action->getFlatActions() as $groupedAction) {
                $this->relabelViewAction($groupedAction);
            }
        }

        // Pages that already place a header Save button (e.g. the category
        // resources with their custom layout) keep their own; every other page
        // gets one prepended so Save is reachable above a long form as well.
        if (! $hasSaveHeaderAction) {
            $saveHeaderAction = $this->getSaveHeaderAction();
            $this->cacheAction($saveHeaderAction);

            array_unshift($this->cachedHeaderActions, $saveHeaderAction);
        }
    }

    /**
     * Rename the record "view" action to the clearer "Zobrazit detail".
     */
    protected function relabelViewAction(Action $action): void
    {
        if ($action->getName() === 'view') {
            $action->label('Zobrazit detail');
        }
    }

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
