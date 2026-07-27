<?php

namespace App\Filament\Support\Help;

use Illuminate\Support\Str;

/**
 * One help article, parsed from a markdown file under resources/help.
 *
 * The raw markdown is carried rather than the rendered HTML: the tree holds every
 * topic but only the open one is ever rendered, so parsing is deferred to
 * {@see self::html()}. Plain text is precomputed instead, because searching needs
 * every topic's body at once and re-rendering the whole corpus per keystroke would
 * be wasteful.
 */
final readonly class HelpTopic
{
    /**
     * @param  array<int, string>  $keywords
     */
    public function __construct(
        public string $id,
        public string $title,
        public ?string $icon,
        public array $keywords,
        public string $sectionId,
        public string $sectionTitle,
        public string $markdown,
        public string $plainText,
    ) {}

    public function html(): string
    {
        return Str::markdown($this->markdown);
    }

    public function excerpt(int $characters = 160): string
    {
        return Str::limit($this->plainText, $characters);
    }

    /**
     * The haystack a query is matched against.
     */
    public function searchable(): string
    {
        return implode(' ', [$this->title, ...$this->keywords, $this->plainText]);
    }
}
