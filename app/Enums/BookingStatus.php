<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Sign-up lifecycle shared by workshop registrations and one-time lesson
 * bookings (course enrollments use their own CourseEnrollmentStatus with an
 * "active" wording). Confirmed and Pending occupy a spot; Cancelled and
 * Waitlist never count toward capacity.
 */
enum BookingStatus: string implements HasColor, HasLabel
{
    case Confirmed = 'confirmed';
    case Pending = 'pending';
    case Cancelled = 'cancelled';
    case Waitlist = 'waitlist';

    /**
     * The statuses that hold a spot in the capacity math.
     *
     * @return array<int, self>
     */
    public static function occupying(): array
    {
        return [self::Confirmed, self::Pending];
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Confirmed => 'Potvrzeno',
            self::Pending => 'Čeká',
            self::Cancelled => 'Zrušeno',
            self::Waitlist => 'Náhradník',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Confirmed => 'success',
            self::Pending => 'warning',
            self::Cancelled => 'danger',
            self::Waitlist => 'warning',
        };
    }
}
