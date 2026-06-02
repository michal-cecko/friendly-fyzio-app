<?php

namespace App\Enums;

enum CourseEnrollmentStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';
    case Waitlist = 'waitlist';
}
