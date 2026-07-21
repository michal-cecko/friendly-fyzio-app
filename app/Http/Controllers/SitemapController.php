<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\EventCategory;
use App\Models\OneOffEvent;
use App\Models\Page;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\TherapistProfile;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class SitemapController extends Controller
{
    /**
     * The public XML sitemap: every canonical public URL, collected from each
     * model's own permalink so the list can never drift from the live routes.
     */
    public function __invoke(): Response
    {
        $urls = collect();

        // Standalone CMS pages (home + published pages). Pages attached to an owner
        // canonicalise to that owner's permalink, which the owner adds below.
        Page::query()
            ->published()
            ->whereNull('pageable_type')
            ->get()
            ->each(fn (Page $page) => $urls->push($page->permalink));

        ServiceCategory::published()->get()
            ->each(fn (ServiceCategory $category) => $urls->push($category->permalink));

        Service::public()->with('category')->get()
            ->each(fn (Service $service) => $urls->push($service->permalink));

        Course::published()->get()
            ->each(fn (Course $course) => $urls->push($course->permalink()));

        EventCategory::published()->get()
            ->each(fn (EventCategory $category) => $urls->push($category->permalink));

        OneOffEvent::published()->with('category')->get()
            ->each(fn (OneOffEvent $event) => $urls->push($event->permalink()));

        TherapistProfile::published()->get()
            ->each(fn (TherapistProfile $therapist) => $urls->push($therapist->permalink));

        return response()
            ->view('sitemap', ['urls' => $this->normalize($urls)])
            ->header('Content-Type', 'application/xml');
    }

    /**
     * @param  Collection<int, string>  $urls
     * @return Collection<int, string>
     */
    private function normalize(Collection $urls): Collection
    {
        return $urls->filter()->unique()->values();
    }
}
