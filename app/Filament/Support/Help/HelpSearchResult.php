<?php

namespace App\Filament\Support\Help;

use Illuminate\Support\HtmlString;

final readonly class HelpSearchResult
{
    public function __construct(
        public HelpTopic $topic,
        public HtmlString $titleHtml,
        public HtmlString $excerptHtml,
    ) {}
}
