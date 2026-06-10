<?php

namespace App\Providers;

use App\Http\Responses\LoginResponse;
use App\Models\Course;
use App\Models\CourseSeries;
use App\Models\OneTimeLesson;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\Workshop;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\View\ActionsIconAlias;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Route users to the correct panel after the single shared login.
        $this->app->bind(LoginResponseContract::class, LoginResponse::class);
    }

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

        FilamentIcon::register([
            ActionsIconAlias::DELETE_ACTION => Heroicon::OutlinedTrash,
            ActionsIconAlias::DELETE_ACTION_GROUPED => Heroicon::OutlinedTrash,
            ActionsIconAlias::FORCE_DELETE_ACTION => Heroicon::OutlinedTrash,
            ActionsIconAlias::FORCE_DELETE_ACTION_GROUPED => Heroicon::OutlinedTrash,
        ]);

        // The icon aliases above only cover table/grouped contexts; set the
        // base button icon so header and standalone delete buttons match too.
        DeleteAction::configureUsing(fn (DeleteAction $action) => $action->icon(Heroicon::OutlinedTrash));
        ForceDeleteAction::configureUsing(fn (ForceDeleteAction $action) => $action->icon(Heroicon::OutlinedTrash));
    }
}
