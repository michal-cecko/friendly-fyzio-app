<?php

namespace App\Http\Controllers;

use App\Filament\Clusters\Lekce\Resources\OneTimeLessons\OneTimeLessonResource;
use App\Models\Course;
use App\Models\OneTimeLesson;
use Illuminate\Contracts\View\View;

class OneTimeLessonController extends Controller
{
    /**
     * Public one-time lesson detail (a specific date of a lesson type): hero
     * with capacity, description from the parent course, the booking section
     * and the course's reviews. Unpublished lessons (or lessons of an
     * unpublished course) are visible only to staff as a preview.
     */
    public function show(Course $course, OneTimeLesson $lesson): View
    {
        abort_unless($lesson->course_id === $course->getKey(), 404);

        $lesson->load(['room', 'instructor.therapistProfile'])->loadCount('activeTakers');
        $course->load('category');

        $user = auth()->user();
        $isCustomer = $user !== null && ! $user->isStaff();
        $hasToken = filled($lesson->presale_token) && request()->query('predprodej') === $lesson->presale_token;
        $unlocked = $hasToken || ($isCustomer && $lesson->isPrivate());

        $isPreview = ! $lesson->isPublished() || ! $course->isPublished();

        abort_if($isPreview && ! $hasToken && ! $this->canPreview(), 404);
        abort_if($lesson->isPrivate() && ! $unlocked && ! $this->canPreview(), 404);

        return view('lessons.show', [
            'course' => $course,
            'lesson' => $lesson,
            'presale' => $unlocked,
            'otherLessons' => $course->upcomingOneTimeLessons()
                ->whereKeyNot($lesson->getKey())
                ->withCount('activeTakers')
                ->limit(3)
                ->get(),
            'reviews' => $course->reviews()->where('visible', true)->latest()->take(6)->get(),
            'isPreview' => $isPreview,
            'adminEditUrl' => $this->adminEditUrl($lesson, OneTimeLessonResource::class),
        ]);
    }
}
