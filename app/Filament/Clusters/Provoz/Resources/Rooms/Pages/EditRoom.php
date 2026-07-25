<?php

namespace App\Filament\Clusters\Provoz\Resources\Rooms\Pages;

use App\Filament\Clusters\Provoz\Resources\Rooms\RoomResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Models\Room;
use Filament\Actions\DeleteAction;

class EditRoom extends BaseEditRecord
{
    protected static string $resource = RoomResource::class;

    public function getTitle(): string
    {
        /** @var Room $record */
        $record = $this->getRecord();

        return 'Upravit místnost '.$record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ActivityLogAction::make(),
        ];
    }
}
