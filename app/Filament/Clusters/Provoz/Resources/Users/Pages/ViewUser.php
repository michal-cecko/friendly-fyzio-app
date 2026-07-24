<?php

namespace App\Filament\Clusters\Provoz\Resources\Users\Pages;

use App\Filament\Clusters\Provoz\Resources\Users\UserResource;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Actions\DeactivateUserAction;
use App\Filament\Support\Actions\ReactivateUserAction;
use App\Filament\Support\Actions\ResetPasswordAction;
use App\Filament\Support\Actions\SendEmailAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use STS\FilamentImpersonate\Actions\Impersonate;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ActionGroup::make([
                Impersonate::make()->record($this->getRecord()),
                SendEmailAction::make(),
                ResetPasswordAction::make(),
                DeactivateUserAction::make(),
                ReactivateUserAction::make(),
                ActivityLogAction::make(),
            ])
                ->label('Akce')
                ->icon(Heroicon::OutlinedEllipsisVertical)
                ->color('gray')
                ->button(),
        ];
    }
}
