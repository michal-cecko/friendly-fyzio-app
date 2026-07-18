<?php

namespace App\Filament\Clusters\Obsah\Resources\InstagramConnections\Pages;

use App\Filament\Clusters\Obsah\Resources\InstagramConnections\InstagramConnectionResource;
use App\Filament\Support\Actions\ActivityLogAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditInstagramConnection extends EditRecord
{
    protected static string $resource = InstagramConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            InstagramConnectionResource::authorizeAction(),
            InstagramConnectionResource::syncAction(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
            ActivityLogAction::make(),
        ];
    }
}
