<?php

namespace App\Http\Controllers;

use App\Filament\Clusters\Obsah\Resources\Pages\PageResource;
use App\Mason\BrickRegistry;
use App\Models\Page;
use Awcodes\Mason\Support\MasonRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PageController extends Controller
{
    public function show(string $slug = '/'): View|RedirectResponse
    {
        $page = Page::query()
            ->when($slug === '/', fn ($query) => $query->where('system_key', 'home'))
            ->when($slug !== '/', fn ($query) => $query->where('slug', $slug))
            ->firstOrFail();

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
