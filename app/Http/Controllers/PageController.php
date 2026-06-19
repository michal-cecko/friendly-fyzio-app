<?php

namespace App\Http\Controllers;

use App\Mason\BrickRegistry;
use App\Models\Page;
use Awcodes\Mason\Support\MasonRenderer;
use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    public function show(string $slug = '/'): View
    {
        $query = Page::query()->published();

        $page = $slug === '/'
            ? $query->where('system_key', 'home')->firstOrFail()
            : $query->where('slug', $slug)->firstOrFail();

        $renderedContent = MasonRenderer::make($page->content ?: [])
            ->bricks(BrickRegistry::all())
            ->toUnsafeHtml();

        return view('pages.show', [
            'page' => $page,
            'renderedContent' => $renderedContent,
        ]);
    }
}
