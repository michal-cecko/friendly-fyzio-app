<?php

namespace App\Http\Controllers;

use App\Filament\Clusters\Kurzy\Resources\OneOffEvents\OneOffEventResource;
use App\Models\OneOffEvent;
use App\Support\Seo\LegacyRedirects;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class OneOffEventController extends Controller
{
    /**
     * Public one-off event detail at /{category}/{event}: hero card with
     * date/place/capacity/price, the description (falling back to the linked
     * course), the sign-up section (all states) and reviews. Unpublished events
     * are visible only to staff as a preview; past ones stay reachable as muted
     * information (spec §3.6).
     *
     * The route is a two-segment catch-all registered after every explicit
     * route, so resolution is manual: an event found under another category
     * 301s to its canonical URL, and an unknown pair falls through to the
     * legacy-redirect map (which used to be served by Route::fallback for
     * these paths) before 404ing.
     */
    public function show(string $categorySlug, string $eventSlug): View|RedirectResponse
    {
        $event = OneOffEvent::query()
            ->with(['category', 'course', 'room.building', 'instructor.staffProfile'])
            ->where('slug', $eventSlug)
            ->first();

        if ($event === null || $event->category === null) {
            return $this->legacyOr404($categorySlug.'/'.$eventSlug);
        }

        // Canonical URL: the event's own category slug.
        if ($event->category->slug !== $categorySlug) {
            return redirect()->to($event->permalink(), 301);
        }

        $event->loadCount('activeTakers');

        $user = auth()->user();
        $isCustomer = $user !== null && ! $user->isStaff();
        $hasToken = filled($event->presale_token) && request()->query('predprodej') === $event->presale_token;
        $unlocked = $hasToken || ($isCustomer && $event->isPrivate());

        $isPreview = ! $event->isPublished();

        abort_if($isPreview && ! $hasToken && ! $this->canPreview(), 404);
        abort_if($event->isPrivate() && ! $unlocked && ! $this->canPreview(), 404);

        // Course-linked events collect reviews on the course (shared with the
        // whole course programme); standalone events carry their own.
        $reviewable = $event->course ?? $event;

        return view('one-off-events.show', [
            'event' => $event,
            'presale' => $unlocked,
            'otherEvents' => OneOffEvent::query()
                ->published()
                ->upcoming()
                ->where('event_category_id', $event->event_category_id)
                ->whereKeyNot($event->getKey())
                ->withCount('activeTakers')
                ->with('category')
                ->orderBy('event_date')
                ->orderBy('start_time')
                ->limit(3)
                ->get(),
            'reviews' => $reviewable->reviews()->where('visible', true)->latest()->take(6)->get(),
            'isPreview' => $isPreview,
            'adminEditUrl' => $this->adminEditUrl($event, OneOffEventResource::class),
        ]);
    }

    protected function legacyOr404(string $path): RedirectResponse
    {
        $target = LegacyRedirects::resolve($path);

        abort_if($target === null, 404);

        return redirect()->to($target, 301);
    }
}
