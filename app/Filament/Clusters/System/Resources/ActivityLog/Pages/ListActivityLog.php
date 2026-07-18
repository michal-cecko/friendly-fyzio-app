<?php

namespace App\Filament\Clusters\System\Resources\ActivityLog\Pages;

use App\Filament\Clusters\System\Resources\ActivityLog\ActivityLogResource;
use Filament\Resources\Pages\ListRecords;

class ListActivityLog extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;
}
