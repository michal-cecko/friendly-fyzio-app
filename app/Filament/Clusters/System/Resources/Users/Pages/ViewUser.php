<?php

namespace App\Filament\Clusters\System\Resources\Users\Pages;

use App\Filament\Clusters\System\Resources\Users\UserResource;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Actions\ResetPasswordAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use STS\FilamentImpersonate\Actions\Impersonate;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Impersonate::make()->record($this->getRecord()),
            ResetPasswordAction::make(),
            EditAction::make(),
            ActivityLogAction::make(),
        ];
    }
}
