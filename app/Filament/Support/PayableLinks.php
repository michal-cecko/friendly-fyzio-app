<?php

namespace App\Filament\Support;

use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\CourseEnrollmentResource;
use App\Filament\Clusters\Kurzy\Resources\LessonBookings\LessonBookingResource;
use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Models\CourseEnrollment;
use App\Models\LessonBooking;
use App\Models\Reservation;

/**
 * Deep links from the Finance cluster back to a payable's own resource page.
 */
final class PayableLinks
{
    public static function url(?object $payable): ?string
    {
        return match (true) {
            $payable instanceof Reservation => ReservationResource::getUrl('view', ['record' => $payable]),
            $payable instanceof CourseEnrollment => CourseEnrollmentResource::getUrl('view', ['record' => $payable]),
            $payable instanceof LessonBooking => LessonBookingResource::getUrl('view', ['record' => $payable]),
            default => null,
        };
    }
}
