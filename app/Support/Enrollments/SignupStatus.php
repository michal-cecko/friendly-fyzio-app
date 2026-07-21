<?php

namespace App\Support\Enrollments;

use App\Enums\BookingStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Models\CourseEnrollment;
use App\Models\OneOffEventBooking;
use Illuminate\Database\Eloquent\Model;

/**
 * Small shared helper for the "does this sign-up currently occupy a spot"
 * question, which differs by model (CourseEnrollmentStatus vs BookingStatus).
 * Used by cancellation (window check + admin action visibility).
 */
class SignupStatus
{
    public static function isActive(CourseEnrollment|OneOffEventBooking $signup): bool
    {
        return $signup instanceof CourseEnrollment
            ? $signup->status === CourseEnrollmentStatus::Active
            : in_array($signup->status, BookingStatus::occupying(), true);
    }

    public static function isSignup(Model $record): bool
    {
        return $record instanceof CourseEnrollment
            || $record instanceof OneOffEventBooking;
    }

    public static function isActiveSignup(Model $record): bool
    {
        return self::isSignup($record) && self::isActive($record);
    }
}
