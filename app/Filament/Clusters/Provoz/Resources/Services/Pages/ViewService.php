<?php

namespace App\Filament\Clusters\Provoz\Resources\Services\Pages;

use App\Filament\Clusters\Provoz\Resources\Services\ServiceResource;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Models\Service;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewService extends ViewRecord
{
    protected static string $resource = ServiceResource::class;

    public function getTitle(): string
    {
        /** @var Service $record */
        $record = $this->getRecord();

        return 'Služba '.$record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
            ActivityLogAction::make(),
        ];
    }
}
