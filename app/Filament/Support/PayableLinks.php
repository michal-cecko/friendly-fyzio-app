<?php

namespace App\Filament\Support;

use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\CourseEnrollmentResource;
use App\Filament\Clusters\Kurzy\Resources\LessonBookings\LessonBookingResource;
use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Models\CourseEnrollment;
use App\Models\LessonBooking;
use App\Models\Reservation;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

/**
 * Deep links from the Finance cluster back to a payable's own resource page.
 */
final class PayableLinks
{
    public static function url(?object $payable): ?string
    {
        return match (true) {
            $payable instanceof Reservation => self::viewUrl(ReservationResource::class, $payable),
            $payable instanceof CourseEnrollment => self::viewUrl(CourseEnrollmentResource::class, $payable),
            $payable instanceof LessonBooking => self::viewUrl(LessonBookingResource::class, $payable),
            default => null,
        };
    }

    /**
     * Payments outlive who may open what they paid for: a therapist reaches
     * Platby but not the Kurzy resources, so the link is dropped rather than
     * pointed at a page that would answer 403.
     *
     * @param  class-string<\Filament\Resources\Resource>  $resource
     */
    private static function viewUrl(string $resource, Model $record): ?string
    {
        if (! $resource::canAccess()) {
            return null;
        }

        return $resource::getUrl('view', ['record' => $record]);
    }
}
