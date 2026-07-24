<?php

namespace App\Filament\Clusters\Provoz\Resources\Clients\Pages;

use App\Filament\Clusters\Provoz\Resources\Clients\ClientResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Actions\ResetPasswordAction;
use App\Filament\Support\Concerns\RendersRelationManagersAsSections;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use STS\FilamentImpersonate\Actions\Impersonate;

class EditClient extends BaseEditRecord
{
    use RendersRelationManagersAsSections;

    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Impersonate::make()->record($this->getRecord()),
            ResetPasswordAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
            ActivityLogAction::make(),
        ];
    }
}
