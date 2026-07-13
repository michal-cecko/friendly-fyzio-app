<?php

namespace App\Filament\Pages;

use App\Filament\Support\Search\AdminSearchResult;
use App\Filament\Support\Search\AdminSearchService;
use App\Filament\Support\Search\RecentSearches;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class Search extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static ?string $title = 'Vyhledávání';

    protected string $view = 'filament.pages.search';

    #[Url(as: 'q')]
    public string $q = '';

    #[Url]
    public bool $includeTrashed = true;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(): void
    {
        $this->rememberSearch();
    }

    public function updatedQ(): void
    {
        $this->rememberSearch();
    }

    /**
     * @return Collection<string, Collection<int, AdminSearchResult>>
     */
    #[Computed]
    public function results(): Collection
    {
        return app(AdminSearchService::class)->search($this->q, $this->includeTrashed);
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function recentSearches(): array
    {
        return app(RecentSearches::class)->all();
    }

    public function searchFor(string $term): void
    {
        $this->q = $term;

        $this->rememberSearch();
    }

    public function forgetRecentSearch(string $term): void
    {
        app(RecentSearches::class)->forget($term);

        unset($this->recentSearches);
    }

    public function clearRecentSearches(): void
    {
        app(RecentSearches::class)->clear();

        unset($this->recentSearches);
    }

    protected function rememberSearch(): void
    {
        $term = trim($this->q);

        if (mb_strlen($term) >= 3) {
            app(RecentSearches::class)->record($term);

            unset($this->recentSearches);
        }
    }
}
