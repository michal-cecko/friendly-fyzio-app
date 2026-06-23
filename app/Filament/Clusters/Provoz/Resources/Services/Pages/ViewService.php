<?php

namespace App\Filament\Clusters\Provoz\Resources\Services\Pages;

use App\Filament\Clusters\Provoz\Resources\Services\ServiceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewService extends ViewRecord
{
    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
