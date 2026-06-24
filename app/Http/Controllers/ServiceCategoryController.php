<?php

namespace App\Http\Controllers;

use App\Filament\Clusters\Obsah\Resources\Pages\PageResource;
use App\Filament\Clusters\Provoz\Resources\ServiceCategories\ServiceCategoryResource;
use App\Mason\BrickRegistry;
use App\Models\ServiceCategory;
use App\Support\Media;
use Awcodes\Mason\Support\MasonRenderer;
use Illuminate\Contracts\View\View;

class ServiceCategoryController extends Controller
{
    public function show(ServiceCategory $category): View
    {
        $categoryPreview = ! $category->isPublished();

        // A hidden/unpublished category is invisible to the public; staff get a preview.
        abort_if($categoryPreview && ! $this->canPreview(), 404);

        $custom = $category->customPage;

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
                ]);
            }
        }

        // Default, fixed, data-driven category page.
        return view('service-categories.show', [
            'category' => $category,
            'services' => $category->services()->public()->orderBy('name')->get(),
            'isPreview' => $categoryPreview,
            'adminEditUrl' => $this->adminEditUrl($category, ServiceCategoryResource::class),
            'seo' => [
                'title' => $category->name,
                'description' => $category->description,
                'image' => Media::url($category->hero_image, '800'),
            ],
        ]);
    }
}
