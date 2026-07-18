<?php

namespace App\Filament\Clusters\Provoz\Resources\Rooms\Pages;

use App\Filament\Clusters\Provoz\Resources\Rooms\RoomResource;
use App\Filament\Support\Actions\ActivityLogAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRoom extends EditRecord
{
    protected static string $resource = RoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ActivityLogAction::make(),
        ];
    }
}
