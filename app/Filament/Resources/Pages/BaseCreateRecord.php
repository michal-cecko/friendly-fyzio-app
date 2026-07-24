<?php

namespace App\Filament\Resources\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Alignment;

/**
 * App-wide base for resource create pages. Drops the "Zrušit" (Cancel) action —
 * it only navigates away and adds noise — and right-aligns the remaining form
 * actions (Create / Create & create another). All create pages should extend
 * this instead of Filament's {@see CreateRecord} directly.
 */
abstract class BaseCreateRecord extends CreateRecord
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
}
