<?php

namespace App\Providers;

use App\Models\Course;
use App\Models\CourseSeries;
use App\Models\OneTimeLesson;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Relation::morphMap([
            'service' => Service::class,
            'course_series' => CourseSeries::class,
            'course' => Course::class,
            'workshop' => Workshop::class,
            'one_time_lesson' => OneTimeLesson::class,
            'reservation' => Reservation::class,
        ]);
    }
}
