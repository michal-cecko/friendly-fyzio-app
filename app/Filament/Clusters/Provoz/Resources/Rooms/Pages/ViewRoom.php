<?php

namespace App\Filament\Clusters\Provoz\Resources\Rooms\Pages;

use App\Filament\Clusters\Provoz\Resources\Rooms\RoomResource;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Models\Room;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRoom extends ViewRecord
{
    protected static string $resource = RoomResource::class;

    public function getTitle(): string
    {
        /** @var Room $record */
        $record = $this->getRecord();

        return 'Místnost '.$record->name;
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
