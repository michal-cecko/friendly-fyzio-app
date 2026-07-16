<?php

namespace App\Observers;

use App\Filament\Clusters\Obsah\Resources\Pages\PageResource;
use App\Filament\Clusters\Provoz\Resources\ServiceCategories\ServiceCategoryResource;
use App\Filament\Clusters\Provoz\Resources\Services\ServiceResource;
use App\Models\Page;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Support\Mentions\StaffMentions;

/**
 * Sends an in-app notification to staff members newly @-mentioned anywhere in a
 * page's Mason brick content. The notification links to where the content is
 * edited: the owning Service/ServiceCategory for custom pages, the page itself
 * otherwise.
 */
class PageObserver
{
    public function created(Page $page): void
    {
        $this->notifyMentions($page, old: null);
    }

    public function updated(Page $page): void
    {
        if ($page->wasChanged('content')) {
            $this->notifyMentions($page, old: $page->getOriginal('content'));
        }
    }

    private function notifyMentions(Page $page, ?array $old): void
    {
        if (! StaffMentions::containsMentions($page->content)) {
            return;
        }

        $actor = auth()->user();

        StaffMentions::notifyNewMentions(
            old: $old,
            new: $page->content,
            author: $actor,
            title: ($actor?->name ?? 'Někdo').' vás zmínil/a v obsahu stránky „'.$page->title.'“',
            url: match (true) {
                $page->pageable instanceof Service => ServiceResource::getUrl('edit', ['record' => $page->pageable]),
                $page->pageable instanceof ServiceCategory => ServiceCategoryResource::getUrl('edit', ['record' => $page->pageable]),
                default => PageResource::getUrl('edit', ['record' => $page]),
            },
        );
    }
}
