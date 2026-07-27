<?php

namespace App\Filament\Support\RelationManagers;

use App\Enums\BookingStatus;
use App\Filament\Clusters\Kurzy\Resources\LessonBookings\LessonBookingResource;
use App\Filament\Support\Actions\SendReviewRequestAction;
use App\Models\Lesson;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * People who bought a seat at this single lesson. On a standalone workshop that
 * is simply its sign-up list; on a lesson of a course série those are the
 * drop-in buyers who took a freed place, sitting alongside the série's own
 * roster in the Docházka tab.
 */
class LessonBookingsRelationManager extends AbstractSignupsRelationManager
{
    protected static string $relationship = 'bookings';

    protected static ?string $title = 'Přihlášky';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedUsers;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return $ownerRecord instanceof Lesson && $ownerRecord->isPartOfSeries()
            ? 'Jednorázové vstupy'
            : 'Přihlášky';
    }

    /**
     * Who is coming is answered by the Docházka list; this tab is the money side
     * of the same people — payment, storno, invoice. On a course lesson nobody
     * has bought yet there is nothing to show.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (! $ownerRecord instanceof Lesson || ! $ownerRecord->isPartOfSeries()) {
            return true;
        }

        return $ownerRecord->bookings()->exists();
    }

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
        return LessonBookingResource::getUrl('view', ['record' => $record]);
    }
}
