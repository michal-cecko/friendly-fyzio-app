<?php

namespace App\Filament\Clusters\Provoz\Resources\Services\Pages;

use App\Filament\Clusters\Provoz\Resources\Services\ServiceResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Support\Actions\ActivityLogAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;

class EditService extends BaseEditRecord
{
    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
            ActivityLogAction::make(),
        ];
    }
}
