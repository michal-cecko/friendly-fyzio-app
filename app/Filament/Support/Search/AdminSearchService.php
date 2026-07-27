<?php

namespace App\Filament\Support\Search;

use App\Filament\Clusters\System\Pages\Concerns\SettingsGroupPage;
use App\Filament\Pages\Help;
use App\Filament\Support\Help\HelpSearch;
use App\Filament\Support\Help\HelpSearchResult;
use App\Models\Setting;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

use function Filament\Support\generate_search_column_expression;
use function Filament\Support\generate_search_term_expression;

/**
 * Searches all globally searchable panel resources, reusing each resource's own
 * global-search configuration (searchable attributes, title, details, URL).
 *
 * Unlike Filament's built-in global search, soft-deleted records can be included
 * and every result carries an explicit trashed flag for the standalone search page.
 */
class AdminSearchService
{
    public const MINIMUM_QUERY_LENGTH = 2;

    public const RESULTS_PER_RESOURCE = 25;

    public function __construct(protected SearchHighlighter $highlighter) {}

    /**
     * @return Collection<int, AdminSearchGroup>
     */
    public function search(string $search, bool $includeTrashed = true): Collection
    {
        $search = trim($search);

        if (mb_strlen($search) < self::MINIMUM_QUERY_LENGTH) {
            return collect();
        }

        $groups = collect();

        foreach (Filament::getCurrentOrDefaultPanel()?->getResources() ?? [] as $resource) {
            /** @var class-string<resource> $resource */
            if (! $resource::canGloballySearch()) {
                continue;
            }

            $found = $this->searchResource($resource, $search, $includeTrashed);

            if ($found->isNotEmpty()) {
                $groups->push(new AdminSearchGroup(
                    label: Str::ucfirst($resource::getPluralModelLabel()),
                    icon: $resource::getNavigationIcon(),
                    results: $found,
                ));
            }
        }

        $settings = $this->searchSettings($search);

        if ($settings->isNotEmpty()) {
            $groups->push(new AdminSearchGroup(
                label: 'Nastavení',
                icon: Heroicon::OutlinedCog6Tooth,
                results: $settings,
            ));
        }

        $help = $this->searchHelp($search);

        if ($help->isNotEmpty()) {
            $groups->push(new AdminSearchGroup(
                label: 'Nápověda',
                icon: Heroicon::OutlinedQuestionMarkCircle,
                results: $help,
            ));
        }

        return $groups;
    }

    /**
     * Search the in-app manual, so someone who does not know a screen exists can
     * still find the article describing it. Unlike settings this is open to every
     * panel user — therapists and lecturers need the manual most.
     *
     * @return Collection<int, AdminSearchResult>
     */
    public function searchHelp(string $search): Collection
    {
        return app(HelpSearch::class)->search($search)
            ->take(self::RESULTS_PER_RESOURCE)
            ->map(fn (HelpSearchResult $result): AdminSearchResult => new AdminSearchResult(
                title: $result->topic->title,
                details: ['Sekce' => $result->topic->sectionTitle],
                url: Help::getUrl(['tema' => $result->topic->id]),
                isTrashed: false,
                titleHtml: $result->titleHtml,
                detailsHtml: ['Sekce' => $this->highlighter->highlight($result->topic->sectionTitle, $search)],
            ))
            ->values();
    }

    /**
     * Search configurable settings by label, helper text (description) or key,
     * deep-linking each hit to its field on the owning settings page. Restricted
     * to admins, mirroring {@see SettingsGroupPage::canAccess()}.
     *
     * @return Collection<int, AdminSearchResult>
     */
    public function searchSettings(string $search): Collection
    {
        if (! (auth()->user()?->isAdmin() ?? false)) {
            return collect();
        }

        $search = trim($search);

        if (mb_strlen($search) < self::MINIMUM_QUERY_LENGTH) {
            return collect();
        }

        $pagesByGroup = $this->settingsPagesByGroup();

        if ($pagesByGroup === []) {
            return collect();
        }

        $words = array_filter(
            preg_split('/\s+/u', $search) ?: [],
            fn (string $word): bool => filled($word),
        );

        return Setting::query()
            ->whereIn('group', array_keys($pagesByGroup))
            ->where(function (Builder $query) use ($words): void {
                foreach ($words as $word) {
                    $query->where(function (Builder $query) use ($word): void {
                        $query->where('label', 'like', "%{$word}%")
                            ->orWhere('description', 'like', "%{$word}%")
                            ->orWhere('key', 'like', "%{$word}%");
                    });
                }
            })
            ->orderBy('group')
            ->orderBy('sort')
            ->limit(self::RESULTS_PER_RESOURCE)
            ->get()
            ->map(function (Setting $setting) use ($pagesByGroup, $search): AdminSearchResult {
                /** @var class-string<SettingsGroupPage> $page */
                $page = $pagesByGroup[$setting->group];

                $title = $setting->label ?: $setting->key;

                $details = array_filter([
                    'Sekce' => $setting->group,
                    'Popis' => $setting->description,
                ], fn (?string $value): bool => filled($value));

                return new AdminSearchResult(
                    title: $title,
                    details: $details,
                    url: $page::getUrl().'#'.$setting->anchor(),
                    isTrashed: false,
                    titleHtml: $this->highlighter->highlight($title, $search),
                    detailsHtml: array_map(
                        fn (string $value) => $this->highlighter->highlight($value, $search),
                        $details,
                    ),
                );
            });
    }

    /**
     * Map each managed Setting `group` to the settings page class that edits it.
     *
     * @return array<string, class-string<SettingsGroupPage>>
     */
    protected function settingsPagesByGroup(): array
    {
        $map = [];

        foreach (Filament::getCurrentOrDefaultPanel()?->getPages() ?? [] as $page) {
            if (is_subclass_of($page, SettingsGroupPage::class)) {
                $map[$page::settingGroup()] = $page;
            }
        }

        return $map;
    }

    /**
     * @param  class-string<resource>  $resource
     * @return Collection<int, AdminSearchResult>
     */
    protected function searchResource(string $resource, string $search, bool $includeTrashed): Collection
    {
        $query = $resource::getGlobalSearchEloquentQuery();

        if ($includeTrashed && in_array(SoftDeletes::class, class_uses_recursive($query->getModel()))) {
            $query->withTrashed();
        }

        $this->applySearchConstraints($query, $resource, $search);

        return $query
            ->limit(self::RESULTS_PER_RESOURCE)
            ->get()
            ->map(function (Model $record) use ($resource, $search): AdminSearchResult {
                $title = (string) $resource::getGlobalSearchResultTitle($record);
                $details = $resource::getGlobalSearchResultDetails($record);

                return new AdminSearchResult(
                    title: $title,
                    details: $details,
                    url: $resource::getGlobalSearchResultUrl($record),
                    isTrashed: method_exists($record, 'trashed') && $record->trashed(),
                    titleHtml: $this->highlighter->highlight($title, $search),
                    detailsHtml: array_map(
                        fn ($value) => $this->highlighter->highlight((string) $value, $search),
                        $details,
                    ),
                );
            });
    }

    /**
     * Word-split search across the resource's globally searchable attributes,
     * modeled on Filament's HasGlobalSearch::applyGlobalSearchAttributeConstraints().
     *
     * @param  class-string<resource>  $resource
     */
    protected function applySearchConstraints(Builder $query, string $resource, string $search): void
    {
        $databaseConnection = $query->getConnection();
        $isForcedCaseInsensitive = $resource::isGlobalSearchForcedCaseInsensitive();

        $search = generate_search_term_expression($search, $isForcedCaseInsensitive, $databaseConnection);

        $searchWords = $resource::shouldSplitGlobalSearchTerms()
            ? array_filter(
                str_getcsv(preg_replace('/\s+/u', ' ', $search) ?? $search, separator: ' ', escape: '\\'),
                fn ($word): bool => filled($word),
            )
            : [$search];

        foreach ($searchWords as $searchWord) {
            $query->where(function (Builder $query) use ($databaseConnection, $isForcedCaseInsensitive, $resource, $searchWord): void {
                $isFirst = true;

                foreach ($resource::getGloballySearchableAttributes() as $attributes) {
                    foreach (Arr::wrap($attributes) as $searchAttribute) {
                        $whereClause = $isFirst ? 'where' : 'orWhere';

                        if (str_contains($searchAttribute, '.')) {
                            $query->{"{$whereClause}Has"}(
                                (string) str($searchAttribute)->beforeLast('.'),
                                fn (Builder $query) => $query->where(
                                    generate_search_column_expression($query->qualifyColumn((string) str($searchAttribute)->afterLast('.')), $isForcedCaseInsensitive, $databaseConnection),
                                    'like',
                                    "%{$searchWord}%",
                                ),
                            );
                        } else {
                            $query->{$whereClause}(
                                generate_search_column_expression($query->qualifyColumn($searchAttribute), $isForcedCaseInsensitive, $databaseConnection),
                                'like',
                                "%{$searchWord}%",
                            );
                        }

                        $isFirst = false;
                    }
                }
            });
        }
    }
}
