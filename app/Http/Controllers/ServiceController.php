<?php

namespace App\Http\Controllers;

use App\Enums\ServiceVisibility;
use App\Filament\Clusters\Obsah\Resources\Pages\PageResource;
use App\Filament\Clusters\Provoz\Resources\Services\ServiceResource;
use App\Mason\BrickRegistry;
use App\Models\Service;
use App\Models\ServiceCategory;
use Awcodes\Mason\Support\MasonRenderer;
use Illuminate\Contracts\View\View;

class ServiceController extends Controller
{
    public function show(ServiceCategory $category, Service $service): View
    {
        $custom = $service->customPage;

        // A published custom page (or a draft previewed by staff) is a deliberately
        // authored public page: it renders regardless of the service's booking
        // visibility (topic pages are intentionally "Hidden" from listings/booking).
        // The custom page's own published state is the gate.
        if ($custom !== null) {
            $customPreview = ! $custom->isPublished();

            if (! $customPreview || $this->canPreview()) {
                $renderedContent = MasonRenderer::make($custom->content ?: [])
                    ->bricks(BrickRegistry::flat())
                    ->toUnsafeHtml();

                return view('pages.show', [
                    'page' => $custom,
                    'renderedContent' => $renderedContent,
                    'isPreview' => $customPreview,
                    'adminEditUrl' => $this->adminEditUrl($custom, PageResource::class),
                ]);
            }
        }

        // Default, data-driven single-service page — gated on the service's public
        // visibility. A hidden/unpublished service is invisible to the public; staff
        // get a preview.
        $servicePreview = $service->visibility !== ServiceVisibility::Public
            || $service->published_at === null
            || $service->published_at->isFuture();

        abort_if($servicePreview && ! $this->canPreview(), 404);

        return view('services.show', [
            'category' => $category,
            'service' => $service,
            'isPreview' => $servicePreview,
            'adminEditUrl' => $this->adminEditUrl($service, ServiceResource::class),
            'seo' => [
                'title' => $service->name,
                'description' => null,
                'image' => null,
            ],
        ]);
    }
}
