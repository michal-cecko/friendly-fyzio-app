<?php

namespace App\Filament\Support\Search;

use Illuminate\Support\HtmlString;

/**
 * Wraps occurrences of the search words in <mark> so the search page can show
 * which substring matched. The input text is HTML-escaped first, so record data
 * can never inject markup.
 */
class SearchHighlighter
{
    protected const MARK_CLASSES = 'rounded-sm bg-primary-500/15 font-semibold text-primary-700 dark:bg-primary-400/20 dark:text-primary-300';

    public function highlight(string $text, string $search): HtmlString
    {
        $escaped = e($text);

        $words = array_filter(
            preg_split('/\s+/u', trim($search)) ?: [],
            fn (string $word): bool => $word !== '',
        );

        if ($words === []) {
            return new HtmlString($escaped);
        }

        $pattern = '/('.implode('|', array_map(
            fn (string $word): string => preg_quote(e($word), '/'),
            $words,
        )).')/iu';

        return new HtmlString((string) preg_replace(
            $pattern,
            '<mark class="'.self::MARK_CLASSES.'">$1</mark>',
            $escaped,
        ));
    }
}
