<?php

namespace App\Enums;

enum CourseSeriesStatus: string
{
    case Open = 'open';
    case Full = 'full';
    case Inactive = 'inactive';
}
