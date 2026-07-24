<?php

namespace App\Filament\Clusters\Kurzy\Resources\OneOffEvents\Pages;

use App\Filament\Clusters\Kurzy\Resources\OneOffEvents\OneOffEventResource;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Concerns\RendersRelationManagersAsSections;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOneOffEvent extends ViewRecord
{
    use RendersRelationManagersAsSections;

    protected static string $resource = OneOffEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
            ActivityLogAction::make(),
        ];
    }
}
