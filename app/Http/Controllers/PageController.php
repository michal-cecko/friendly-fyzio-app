<?php

namespace App\Http\Controllers;

use App\Filament\Clusters\Obsah\Resources\Pages\PageResource;
use App\Mason\BrickRegistry;
use App\Models\EventCategory;
use App\Models\Page;
use App\Support\Seo\LegacyRedirects;
use Awcodes\Mason\Support\MasonRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PageController extends Controller
{
    public function show(string $slug = '/'): View|RedirectResponse
    {
        // The retired "Jednorázové lekce" tab of the course archive lives on as
        // its own category page; keep old deep links working.
        if ($slug === 'kurzy' && request()->query('typ') === 'lekce') {
            $lekce = EventCategory::query()->where('slug', 'jednorazove-lekce')->first();

            if ($lekce !== null) {
                return redirect()->to($lekce->permalink, 301);
            }
        }

        // Event category landing pages resolve BEFORE CMS pages: a category's
        // custom page has `pageable` set, and the owned-page redirect below
        // would otherwise bounce the category URL to itself forever.
        if ($slug !== '/') {
            $category = EventCategory::query()->where('slug', $slug)->first();

            if ($category !== null) {
                return app(EventCategoryController::class)->show($category);
            }
        }

        $page = Page::query()
            ->when($slug === '/', fn ($query) => $query->where('system_key', 'home'))
            ->when($slug !== '/', fn ($query) => $query->where('slug', $slug))
            ->first();

        // No CMS page for this slug — it may be an old live-site URL that moved
        // to the new scheme (301) or a genuine 404.
        if ($page === null) {
            $target = LegacyRedirects::resolve($slug);

            abort_if($target === null, 404);

            return redirect()->to($target, 301);
        }

        // A page attached to an owner (e.g. a category) is canonically served at
        // the owner's URL — redirect its own slug there so URLs never diverge.
        if ($page->pageable !== null) {
            return redirect()->to($page->permalink, 301);
        }

        $isPreview = ! $page->isPublished();

        // Unpublished pages are visible only to staff (admins/managers) as a preview.
        abort_if($isPreview && ! $this->canPreview(), 404);

        $renderedContent = MasonRenderer::make($page->content ?: [])
            ->bricks(BrickRegistry::flat())
            ->toUnsafeHtml();

        return view('pages.show', [
            'page' => $page,
            'renderedContent' => $renderedContent,
            'isPreview' => $isPreview,
            'adminEditUrl' => $this->adminEditUrl($page, PageResource::class),
        ]);
    }
}
