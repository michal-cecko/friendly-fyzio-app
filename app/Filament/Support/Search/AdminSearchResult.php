<?php

namespace App\Filament\Support\Search;

use Illuminate\Support\HtmlString;

final readonly class AdminSearchResult
{
    /**
     * @param  array<string, string>  $details
     * @param  array<string, HtmlString>  $detailsHtml
     */
    public function __construct(
        public string $title,
        public array $details,
        public ?string $url,
        public bool $isTrashed,
        public HtmlString $titleHtml,
        public array $detailsHtml,
    ) {}
}
