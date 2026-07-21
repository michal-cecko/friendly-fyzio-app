<?php

namespace App\Http\Controllers;

use App\Filament\Clusters\Kurzy\Resources\EventCategories\EventCategoryResource;
use App\Filament\Clusters\Obsah\Resources\Pages\PageResource;
use App\Mason\BrickRegistry;
use App\Models\EventCategory;
use App\Support\Media;
use Awcodes\Mason\Support\MasonRenderer;
use Illuminate\Contracts\View\View;

/**
 * Public landing page of a one-off event category at /{slug} ("Workshopy",
 * "Jednorázové lekce", …). Invoked from PageController's single-segment
 * catch-all — category slugs resolve BEFORE CMS pages so a category's own
 * custom page never 301s back to itself.
 */
class EventCategoryController extends Controller
{
    public function show(EventCategory $category): View
    {
        $categoryPreview = ! $category->isPublished();

        // A hidden/unpublished category is invisible to the public; staff get a preview.
        abort_if($categoryPreview && ! $this->canPreview(), 404);

        $custom = $category->customPage;

        $breadcrumbs = [
            ['label' => $category->name, 'url' => null],
        ];

        // A published custom page (or a draft previewed by staff) overrides the
        // default layout and renders its bricks at the category URL.
        if ($custom !== null) {
            $customPreview = ! $custom->isPublished();

            if (! $customPreview || $this->canPreview()) {
                $renderedContent = MasonRenderer::make($custom->content ?: [])
                    ->bricks(BrickRegistry::flat())
                    ->toUnsafeHtml();

                return view('pages.show', [
                    'page' => $custom,
                    'renderedContent' => $renderedContent,
                    'isPreview' => $categoryPreview || $customPreview,
                    'adminEditUrl' => $this->adminEditUrl($custom, PageResource::class),
                    'breadcrumbs' => $breadcrumbs,
                ]);
            }
        }

        // Default, data-driven category page: hero + the pre-filtered archive.
        return view('event-categories.show', [
            'category' => $category,
            'isPreview' => $categoryPreview,
            'adminEditUrl' => $this->adminEditUrl($category, EventCategoryResource::class),
            'breadcrumbs' => $breadcrumbs,
            'seo' => [
                'title' => $category->name,
                'description' => $category->description,
                'image' => Media::url($category->featured_image, '800'),
            ],
        ]);
    }
}
