<?php

namespace App\Filament\Support\Help;

use App\Filament\Support\Search\SearchHighlighter;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Searches the help articles. The corpus is a few dozen files held in memory, so
 * this is plain string matching rather than anything database-backed.
 *
 * Matching is diacritic-insensitive: staff type "prihlaseni" as readily as
 * "přihlášení", and an admin who cannot find the article is back to guessing.
 */
class HelpSearch
{
    public const MINIMUM_QUERY_LENGTH = 2;

    public function __construct(
        protected HelpRepository $repository,
        protected SearchHighlighter $highlighter,
    ) {}

    /**
     * @return Collection<int, HelpSearchResult>
     */
    public function search(string $query): Collection
    {
        $query = trim($query);

        if (mb_strlen($query) < self::MINIMUM_QUERY_LENGTH) {
            return collect();
        }

        $words = $this->words($query);

        if ($words === []) {
            return collect();
        }

        return $this->repository->topics()
            ->filter(fn (HelpTopic $topic): bool => $this->matches($topic, $words))
            // One composite key rather than sortBy([...]): passing an array makes
            // Laravel treat each closure as a two-argument comparator instead of a
            // value extractor, and the ordering comes out meaningless.
            ->sortBy(fn (HelpTopic $topic): string => $this->relevance($topic, $words).'|'.$this->fold($topic->title))
            ->map(fn (HelpTopic $topic): HelpSearchResult => new HelpSearchResult(
                topic: $topic,
                titleHtml: $this->highlighter->highlight($topic->title, $query),
                excerptHtml: $this->highlighter->highlight($this->excerpt($topic, $query), $query),
            ))
            ->values();
    }

    /**
     * Every word must appear somewhere in the topic — the same AND semantics the
     * record search uses.
     *
     * @param  array<int, string>  $words
     */
    protected function matches(HelpTopic $topic, array $words): bool
    {
        $haystack = $this->fold($topic->searchable());

        foreach ($words as $word) {
            if (! str_contains($haystack, $word)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Where the hit was found — lower sorts first. An article *about* přihlášení is
     * a better answer than one that merely mentions the word in passing, and the
     * comparison has to be folded like the query or nothing would ever score above
     * the body.
     *
     * @param  array<int, string>  $words
     */
    protected function relevance(HelpTopic $topic, array $words): int
    {
        $title = $this->fold($topic->title);
        $keywords = $this->fold(implode(' ', $topic->keywords));

        $inTitle = 0;
        $inKeywords = 0;

        foreach ($words as $word) {
            $inTitle += str_contains($title, $word) ? 1 : 0;
            $inKeywords += str_contains($keywords, $word) ? 1 : 0;
        }

        return match (true) {
            $inTitle === count($words) => 0,
            $inTitle > 0 => 1,
            $inKeywords > 0 => 2,
            default => 3,
        };
    }

    /**
     * A window of body text around the first hit, so the result list shows the
     * sentence the word was found in rather than always the article's opening.
     */
    protected function excerpt(HelpTopic $topic, string $query): string
    {
        $first = $this->words($query)[0] ?? '';
        $position = mb_strpos($this->fold($topic->plainText), $first);

        if ($position === false || $position < 80) {
            return $topic->excerpt();
        }

        return '… '.Str::limit(mb_substr($topic->plainText, $position - 40), 160);
    }

    /**
     * @return array<int, string>
     */
    protected function words(string $query): array
    {
        return array_values(array_filter(
            preg_split('/\s+/u', $this->fold($query)) ?: [],
            fn (string $word): bool => $word !== '',
        ));
    }

    /**
     * Lower-case and strip diacritics so "termín" and "termin" are the same query.
     */
    protected function fold(string $value): string
    {
        return Str::lower(Str::ascii($value));
    }
}
