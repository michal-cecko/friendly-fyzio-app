<?php

namespace App\Filament\Support\RelationManagers;

use App\Enums\BookingStatus;
use App\Filament\Clusters\Lekce\Resources\OneTimeLessonBookings\OneTimeLessonBookingResource;
use App\Filament\Support\Actions\SendReviewRequestAction;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class OneTimeLessonBookingsRelationManager extends AbstractSignupsRelationManager
{
    protected static string $relationship = 'bookings';

    protected static ?string $title = 'Rezervace';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedUsers;

    protected function statusOptions(): string
    {
        return BookingStatus::class;
    }

    protected function extraRecordActions(): array
    {
        return [
            SendReviewRequestAction::make(),
        ];
    }

    protected function detailUrl(Model $record): string
    {
        return OneTimeLessonBookingResource::getUrl('view', ['record' => $record]);
    }
}
