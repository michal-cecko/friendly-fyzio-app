<?php

namespace App\Http\Controllers;

use App\Enums\PageStatus;
use App\Enums\UserRole;
use App\Mason\BrickRegistry;
use App\Models\Page;
use Awcodes\Mason\Support\MasonRenderer;
use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    public function show(string $slug = '/'): View
    {
        $page = Page::query()
            ->when($slug === '/', fn ($query) => $query->where('system_key', 'home'))
            ->when($slug !== '/', fn ($query) => $query->where('slug', $slug))
            ->firstOrFail();

        $isPreview = $page->status !== PageStatus::Published;

        // Unpublished pages are visible only to staff (admins/managers) as a preview.
        abort_if($isPreview && ! $this->canPreview(), 404);

        $renderedContent = MasonRenderer::make($page->content ?: [])
            ->bricks(BrickRegistry::flat())
            ->toUnsafeHtml();

        return view('pages.show', [
            'page' => $page,
            'renderedContent' => $renderedContent,
            'isPreview' => $isPreview,
        ]);
    }

    private function canPreview(): bool
    {
        $user = auth()->user();

        return $user !== null && in_array($user->role, [UserRole::Admin, UserRole::Therapist], true);
    }
}
