<?php

namespace App\Http\Controllers;

use App\Enums\CourseSeriesVisibility;
use App\Enums\OfferState;
use App\Filament\Clusters\Kurzy\Resources\Courses\CourseResource;
use App\Models\Course;
use App\Models\CourseSeries;
use Illuminate\Contracts\View\View;

class CourseController extends Controller
{
    /**
     * Public course detail: hero with the presented series' capacity, lesson
     * schedule, sign-up section (all states) and reviews. Archive cards link a
     * specific run via ?termin={id} (honored only for public, not-yet-ended
     * runs); a valid hidden-link token (?predprodej=…) beats it and unlocks
     * the page and the form even while the course/series is unpublished,
     * Inactive or private.
     */
    public function show(Course $course): View
    {
        $course->load([
            'category',
            'instructor.staffProfile',
            'series' => fn ($query) => $query
                ->withCount('activeTakers')
                ->withCount('lessons')
                ->withCount(['lessons as remaining_lessons_count' => fn ($lessons) => $lessons->whereDate('lesson_date', '>=', today())])
                ->orderBy('start_date'),
        ]);

        $user = auth()->user();
        $isCustomer = $user !== null && ! $user->isStaff();

        $presaleSeries = null;

        if (filled($token = request()->query('predprodej'))) {
            $presaleSeries = $course->series->firstWhere('presale_token', $token);
        }

        $requestedSeries = null;
        $requestedUnlocked = false;

        if ($presaleSeries === null && filled($seriesId = request()->query('termin'))) {
            $candidate = $course->series->first(fn (CourseSeries $candidate): bool => $candidate->getKey() === $seriesId
                && ! $candidate->hasEnded());

            if ($candidate?->visibility === CourseSeriesVisibility::Public) {
                $requestedSeries = $candidate;
            } elseif ($candidate?->isPrivate() && $isCustomer) {
                // A logged-in customer may open (and enrol in) a private run directly.
                $requestedSeries = $candidate;
                $requestedUnlocked = true;
            }
        }

        $isPreview = ! $course->isPublished();

        abort_if($isPreview && $presaleSeries === null && ! $this->canPreview(), 404);

        $series = $presaleSeries ?? $requestedSeries ?? $course->currentSeries();
        $unlocked = $presaleSeries !== null || $requestedUnlocked;

        return view('courses.show', [
            'course' => $course,
            'series' => $series,
            'presale' => $unlocked,
            'state' => $unlocked && $series !== null
                ? $series->offerStateForPresale()
                : ($series?->offerState() ?? OfferState::Inactive),
            'seriesLessons' => $series?->lessons()->with('room')->get() ?? collect(),
            'upcomingLessons' => $course->upcomingPublicLessons()->withOccupancyCounts()->with(['room', 'category'])->get(),
            'reviews' => $course->reviews()->where('visible', true)->latest()->take(6)->get(),
            'isPreview' => $isPreview,
            'adminEditUrl' => $this->adminEditUrl($course, CourseResource::class),
        ]);
    }
}
