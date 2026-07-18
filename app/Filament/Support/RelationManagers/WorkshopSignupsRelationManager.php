<?php

namespace App\Filament\Support\RelationManagers;

use App\Enums\BookingStatus;
use App\Filament\Clusters\Workshopy\Resources\WorkshopRegistrations\WorkshopRegistrationResource;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class WorkshopSignupsRelationManager extends AbstractSignupsRelationManager
{
    protected static string $relationship = 'registrations';

    protected static ?string $title = 'Registrace';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedUsers;

    protected function statusOptions(): string
    {
        return BookingStatus::class;
    }

    protected function detailUrl(Model $record): string
    {
        return WorkshopRegistrationResource::getUrl('view', ['record' => $record]);
    }
}
