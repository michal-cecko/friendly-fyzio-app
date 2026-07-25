<?php

namespace App\Filament\Clusters\Kurzy\Resources\OneOffEventBookings\Pages;

use App\Filament\Clusters\Kurzy\Resources\OneOffEventBookings\OneOffEventBookingResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Models\OneOffEventBooking;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;

class EditOneOffEventBooking extends BaseEditRecord
{
    protected static string $resource = OneOffEventBookingResource::class;

    public function getTitle(): string
    {
        /** @var OneOffEventBooking $record */
        $record = $this->getRecord();

        return 'Upravit přihlášku na akci '.($record->client?->name ?? 'bez klienta');
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ActivityLogAction::make(),
        ];
    }
}
