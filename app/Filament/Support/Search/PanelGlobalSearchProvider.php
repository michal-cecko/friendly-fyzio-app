<?php

namespace App\Filament\Support\Search;

use Filament\GlobalSearch\GlobalSearchResult;
use Filament\GlobalSearch\GlobalSearchResults;
use Filament\GlobalSearch\Providers\DefaultGlobalSearchProvider;
use Illuminate\Support\Collection;

/**
 * Extends Filament's record-only topbar search with the same non-record sources
 * the standalone search page offers, so a settings field or a manual article is
 * found from the topbar too instead of only from /admin/search.
 *
 * Records still come from the default provider — this only appends categories.
 */
class PanelGlobalSearchProvider extends DefaultGlobalSearchProvider
{
    /**
     * Kept well below the per-resource limit: the topbar is a dropdown, and a long
     * tail of settings would push the record hits people usually want off screen.
     */
    public const RESULTS_PER_CATEGORY = 5;

    public function __construct(protected AdminSearchService $search) {}

    public function getResults(string $query): ?GlobalSearchResults
    {
        $builder = parent::getResults($query) ?? GlobalSearchResults::make();

        $this->addCategory($builder, 'Nastavení', $this->search->searchSettings($query));
        $this->addCategory($builder, 'Nápověda', $this->search->searchHelp($query));

        return $builder;
    }

    /**
     * @param  Collection<int, AdminSearchResult>  $results
     */
    protected function addCategory(GlobalSearchResults $builder, string $label, Collection $results): void
    {
        if ($results->isEmpty()) {
            return;
        }

        $builder->category($label, $results
            ->take(self::RESULTS_PER_CATEGORY)
            ->map(fn (AdminSearchResult $result): GlobalSearchResult => new GlobalSearchResult(
                title: $result->title,
                url: $result->url ?? '#',
                details: $result->details,
            ))
            ->values()
            ->all());
    }
}
