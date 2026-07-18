<?php

namespace App\Filament\Clusters\Provoz\Resources\Services\Pages;

use App\Filament\Clusters\Provoz\Resources\Services\ServiceResource;
use App\Filament\Support\Actions\ActivityLogAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewService extends ViewRecord
{
    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ActivityLogAction::make(),
        ];
    }
}
