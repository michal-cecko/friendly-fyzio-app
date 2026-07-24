<?php

namespace App\Filament\Clusters\Provoz\Resources\Rooms\Pages;

use App\Filament\Clusters\Provoz\Resources\Rooms\RoomResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Support\Actions\ActivityLogAction;
use Filament\Actions\DeleteAction;

class EditRoom extends BaseEditRecord
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
